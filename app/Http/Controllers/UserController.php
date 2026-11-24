<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PublicLeadForm;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);
        $actor = $request->user();
        $filters = $request->only('search', 'role', 'campaign_id', 'status');
        $search = trim((string) ($filters['search'] ?? ''));

        $users = User::query()
            ->with(['campaigns:id,name'])
            ->withCount('assignedLeads')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")))
            ->when(Role::tryFrom((string) ($filters['role'] ?? '')), fn ($q, $role) => $q->where('role', $role->value))
            ->when(ctype_digit((string) ($filters['campaign_id'] ?? '')), fn ($q) => $q->whereHas('campaigns', fn ($c) => $c->where('campaigns.id', (int) $filters['campaign_id'])))
            ->when(in_array($filters['status'] ?? null, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(15)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile,
                'role' => $u->role->value, 'role_label' => $u->role->label(),
                'campaigns' => $u->campaigns->pluck('name'),
                'is_active' => $u->is_active, 'assigned_leads_count' => $u->assigned_leads_count,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'can_toggle' => $actor->id !== $u->id,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::options(),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', $this->formOptions());
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);
        $actor = $request->user();
        $data = $this->guardAssignment($actor, $request->validated());

        $user = DB::transaction(function () use ($data) {
            $user = new User(collect($data)->only('name', 'email', 'mobile', 'password')->all());
            $this->applyAccess($user, $data);
            $user->is_active = $data['is_active'] ?? true;
            $user->save();
            $user->campaigns()->sync($data['campaign_ids']);
            $user->syncPermissions($data['permissions']);
            if ($user->isAdmin()) {
                PublicLeadForm::ensureFor($user, request()->user()->id);
            }

            return $user;
        });

        ActivityLogger::log('users', 'user.created', "User {$user->name} ({$user->role->label()}) created", $user->id, null, $this->auditState($user));

        return redirect()->route('users.show', $user)->with('success', 'User created.');
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);
        $actor = $request->user();
        $user->load(['campaigns:id,name,code', 'permissions', 'publicForm']);

        // Managers: leads assigned to them. Admins: leads they own.
        $assignedLeads = Lead::query()->visibleTo($actor)->where($user->isAdmin() ? 'admin_id' : 'assigned_to', $user->id)
            ->with(['campaign:id,name', 'leadStatus:id,name,color', 'paymentStatus:id,name,color', 'assignee:id,name'])
            ->latest('id')->paginate(10, ['*'], 'leads_page')->withQueryString()
            ->through(fn (Lead $l) => [
                'id' => $l->id, 'lead_number' => $l->lead_number, 'name' => $l->full_name, 'mobile' => $l->mobile,
                'campaign' => $l->campaign?->name, 'assignee' => $l->assignee?->name, 'lead_date' => $l->lead_date?->format('Y-m-d'),
                'lead_status' => $l->leadStatus?->only('name', 'color'), 'payment_status' => $l->paymentStatus?->only('name', 'color'),
            ]);

        $visibleLeadIds = Lead::query()->visibleTo($actor)->select('leads.id');
        $leadActivity = LeadActivity::query()->where('user_id', $user->id)->whereIn('lead_id', $visibleLeadIds)
            ->with('lead:id,lead_number')->latest('created_at')->limit(15)->get()
            ->map(fn ($a) => ['id' => 'l'.$a->id, 'description' => $a->description, 'lead_id' => $a->lead_id, 'lead_number' => $a->lead?->lead_number, 'created_at' => $a->created_at?->toIso8601String()]);

        $systemActivity = $actor->isSuperAdmin()
            ? ActivityLog::query()->where('user_id', $user->id)->latest('created_at')->limit(15)->get()
                ->map(fn ($a) => ['id' => 's'.$a->id, 'description' => $a->description, 'module' => $a->module, 'created_at' => $a->created_at?->toIso8601String()])
            : [];

        return Inertia::render('Users/Show', [
            'user' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile,
                'role' => $user->role->value, 'role_label' => $user->role->label(),
                'campaigns' => $user->campaigns->map->only('id', 'name', 'code'),
                'public_form' => $user->publicForm ? ['url' => $user->publicForm->url(), 'is_active' => $user->publicForm->is_active, 'submissions_count' => $user->publicForm->submissions_count] : null,
                'permissions' => collect($user->permissionValues())->map(fn ($p) => Permission::from($p)->label())->values(),
                'lead_visibility' => $user->lead_visibility,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'assignedLeads' => $assignedLeads,
            'leadActivity' => $leadActivity,
            'systemActivity' => $systemActivity,
            'can' => ['update' => $actor->can('update', $user), 'toggle' => $actor->can('toggleStatus', $user)],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);
        $user->load(['campaigns:id', 'permissions']);

        return Inertia::render('Users/Edit', [
            ...$this->formOptions(),
            'user' => [
                ...$user->only('id', 'name', 'email', 'mobile', 'is_active', 'lead_visibility'),
                'role' => $user->role->value,
                'campaign_ids' => $user->campaigns->pluck('id'),
                'permissions' => $user->permissionValues(),
            ],
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $actor = $request->user();
        $data = $this->guardAssignment($actor, $request->validated(), $user);
        $user->load(['campaigns:id,name', 'permissions']);
        $before = $this->auditState($user);

        DB::transaction(function () use ($user, $data, $actor) {
            $user->fill(collect($data)->only('name', 'email', 'mobile')->all());
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $this->applyAccess($user, $data);
            if ($actor->id !== $user->id && array_key_exists('is_active', $data)) {
                $user->is_active = $data['is_active'];
            }
            $user->save();
            $user->campaigns()->sync($data['campaign_ids']);
            $user->syncPermissions($data['permissions']);
            if ($user->isAdmin()) {
                PublicLeadForm::ensureFor($user, $actor->id);
            }
        });

        $user->load(['campaigns:id,name', 'permissions']);
        [$old, $new] = ActivityLogger::diff($before, $this->auditState($user));
        if (! empty($data['password'])) {
            $new['password'] = 'changed';
        }
        if ($new) {
            ActivityLogger::log('users', 'user.updated', "User {$user->name} updated", $user->id, $old, $new);
        }

        return redirect()->route('users.show', $user)->with('success', 'User updated.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);
        $user->is_active = ! $user->is_active;
        $user->save();
        if (! $user->is_active) {
            // Kill any existing sessions immediately.
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $state = $user->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log('users', "user.{$state}", "User {$user->name} {$state}", $user->id, ['is_active' => ! $user->is_active], ['is_active' => $user->is_active]);

        return back()->with('success', "User {$state}.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $data = $request->validate(['password' => ['required', 'confirmed', Password::defaults(), 'max:255']]);
        $user->password = $data['password'];
        $user->setRememberToken(null);
        $user->save();
        if ($request->user()->id !== $user->id) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        ActivityLogger::log('users', 'user.password_reset', "Password reset for {$user->name}", $user->id);

        return back()->with('success', 'Password reset.');
    }

    // ---- Helpers ------------------------------------------------------------------------

    /** Normalises role-dependent data: Super Admins have no campaigns or permissions. */
    private function guardAssignment(User $actor, array $data, ?User $target = null): array
    {
        $role = Role::from($data['role']);
        $data['campaign_ids'] = array_values(array_unique(array_map('intval', $data['campaign_ids'] ?? [])));
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));

        if ($target && $target->id === $actor->id && $role !== $actor->role) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }
        if ($role === Role::SuperAdmin) {
            $data['campaign_ids'] = [];
            $data['permissions'] = [];
        }

        return $data;
    }

    private function applyAccess(User $user, array $data): void
    {
        $user->role = Role::from($data['role']);
        $user->lead_visibility = $user->role === Role::Manager ? ($data['lead_visibility'] ?? User::VISIBILITY_CAMPAIGN) : User::VISIBILITY_CAMPAIGN;
        $user->flushAccessCache();
    }

    private function formOptions(): array
    {
        return [
            'roles' => Role::options(),
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'permissions' => Permission::options(),
            'defaultPermissions' => collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => array_map(fn ($p) => $p->value, Permission::defaultsFor($r))]),
        ];
    }

    private function auditState(User $user): array
    {
        return [
            'name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile,
            'role' => $user->role->value, 'is_active' => $user->is_active,
            'lead_visibility' => $user->lead_visibility,
            'campaigns' => $user->campaigns()->orderBy('name')->pluck('name')->all(),
            'permissions' => $user->permissions()->orderBy('permission')->pluck('permission')->all(),
        ];
    }
}
