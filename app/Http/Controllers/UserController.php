<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadActivity;
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
        $filters = $request->only('search', 'role', 'branch_id', 'status');
        $search = trim((string) ($filters['search'] ?? ''));

        $users = User::query()->manageableBy($actor)
            ->with(['branch:id,name', 'campaigns:id,name'])
            ->withCount('assignedLeads')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")))
            ->when(Role::tryFrom((string) ($filters['role'] ?? '')), fn ($q, $role) => $q->where('role', $role->value))
            ->when(ctype_digit((string) ($filters['branch_id'] ?? '')), fn ($q) => $q->where('branch_id', (int) $filters['branch_id']))
            ->when(in_array($filters['status'] ?? null, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(15)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile,
                'role' => $u->role->value, 'role_label' => $u->role->label(),
                'branch' => $u->branch?->name, 'campaigns' => $u->campaigns->pluck('name'),
                'is_active' => $u->is_active, 'assigned_leads_count' => $u->assigned_leads_count,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'can_toggle' => $actor->id !== $u->id,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => $this->allowedRoles($actor),
            'branches' => Branch::query()->accessibleBy($actor)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Create', $this->formOptions($request->user()));
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

            return $user;
        });

        ActivityLogger::log('users', 'user.created', "User {$user->name} ({$user->role->label()}) created", $user->id, null, $this->auditState($user));

        return redirect()->route('users.show', $user)->with('success', 'User created.');
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorize('view', $user);
        $actor = $request->user();
        $user->load(['branch:id,name', 'campaigns:id,name,code', 'permissions']);

        $assignedLeads = Lead::query()->visibleTo($actor)->where('assigned_to', $user->id)
            ->with(['campaign:id,name', 'leadStatus:id,name,color', 'paymentStatus:id,name,color', 'branch:id,name'])
            ->latest('id')->paginate(10, ['*'], 'leads_page')->withQueryString()
            ->through(fn (Lead $l) => [
                'id' => $l->id, 'lead_number' => $l->lead_number, 'name' => $l->full_name, 'mobile' => $l->mobile,
                'campaign' => $l->campaign?->name, 'branch' => $l->branch?->name, 'lead_date' => $l->lead_date?->format('Y-m-d'),
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
                'branch' => $user->branch?->name, 'campaigns' => $user->campaigns->map->only('id', 'name', 'code'),
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
            ...$this->formOptions($request->user()),
            'user' => [
                ...$user->only('id', 'name', 'email', 'mobile', 'branch_id', 'is_active', 'lead_visibility'),
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

    /**
     * Enforces what the acting user may grant: roles, branch, campaigns and permissions.
     * Returns the normalised data (campaign_ids/permissions always arrays).
     */
    private function guardAssignment(User $actor, array $data, ?User $target = null): array
    {
        $role = Role::from($data['role']);
        $data['campaign_ids'] = array_values(array_unique(array_map('intval', $data['campaign_ids'] ?? [])));
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));

        if (! in_array($role->value, array_column($this->allowedRoles($actor), 'value'), true)) {
            throw ValidationException::withMessages(['role' => 'You cannot assign this role.']);
        }
        if ($target && $target->id === $actor->id && $role !== $actor->role) {
            throw ValidationException::withMessages(['role' => 'You cannot change your own role.']);
        }

        if ($role === Role::SuperAdmin) {
            $data['branch_id'] = null;
            $data['campaign_ids'] = [];
            $data['permissions'] = [];

            return $data;
        }

        $branchId = (int) $data['branch_id'];
        if (! $actor->isSuperAdmin() && $branchId !== (int) $actor->branch_id) {
            throw ValidationException::withMessages(['branch_id' => 'You can only manage users in your own branch.']);
        }

        $validCampaigns = Campaign::query()->inBranch($branchId)->accessibleBy($actor)->pluck('campaigns.id')->all();
        if (array_diff($data['campaign_ids'], $validCampaigns)) {
            throw ValidationException::withMessages(['campaign_ids' => 'Select only campaigns that are assigned to the selected branch'.($actor->isSuperAdmin() ? '.' : ' and to you.')]);
        }
        if ($target && ! $actor->isSuperAdmin()) {
            // Keep campaign links the Branch Admin cannot see (they were granted by a Super Admin).
            $hidden = $target->campaigns()->whereNotIn('campaigns.id', $actor->campaignIds() ?: [0])->pluck('campaigns.id')->all();
            $data['campaign_ids'] = array_values(array_unique([...$data['campaign_ids'], ...$hidden]));
        }

        $grantable = $this->grantablePermissions($actor, $role);
        if (array_diff($data['permissions'], $grantable)) {
            throw ValidationException::withMessages(['permissions' => 'You cannot grant one or more of the selected permissions.']);
        }

        return $data;
    }

    private function applyAccess(User $user, array $data): void
    {
        $user->role = Role::from($data['role']);
        $user->branch_id = $data['branch_id'] ?? null;
        $user->lead_visibility = $user->role === Role::Manager ? ($data['lead_visibility'] ?? User::VISIBILITY_CAMPAIGN) : User::VISIBILITY_CAMPAIGN;
        $user->flushAccessCache();
    }

    private function allowedRoles(User $actor): array
    {
        $roles = $actor->isSuperAdmin() ? Role::cases() : [Role::Manager];

        return array_map(fn (Role $r) => ['value' => $r->value, 'label' => $r->label()], $roles);
    }

    /** @return array<string> */
    private function grantablePermissions(User $actor, Role $role): array
    {
        $values = array_map(fn ($p) => $p->value, Permission::forRole($role));
        if ($actor->isSuperAdmin()) {
            return $values;
        }

        // Branch Admins can only delegate permissions they hold themselves (never manage_users).
        return array_values(array_intersect($values, $actor->permissionValues(), array_map(fn ($p) => $p->value, Permission::forRole(Role::Manager))));
    }

    private function formOptions(User $actor): array
    {
        $branches = Branch::query()->accessibleBy($actor)->with(['campaigns' => fn ($q) => $q->accessibleBy($actor)->select('campaigns.id', 'campaigns.name', 'campaigns.is_active')])
            ->orderBy('name')->get(['id', 'name', 'is_active']);

        return [
            'roles' => $this->allowedRoles($actor),
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id, 'name' => $b->name, 'is_active' => $b->is_active,
                'campaigns' => $b->campaigns->map->only('id', 'name', 'is_active')->values(),
            ]),
            'permissions' => collect(Permission::options())->map(function ($p) use ($actor) {
                $p['roles'] = array_values(array_filter($p['roles'], fn ($r) => in_array($p['value'], $this->grantablePermissions($actor, Role::from($r)), true)));

                return $p;
            })->filter(fn ($p) => $p['roles'])->values(),
            'defaultPermissions' => collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => array_values(array_intersect(
                array_map(fn ($p) => $p->value, Permission::defaultsFor($r)),
                $r === Role::SuperAdmin ? [] : $this->grantablePermissions($actor, $r),
            ))]),
            'branchLocked' => ! $actor->isSuperAdmin(),
            'actorBranchId' => $actor->branch_id,
        ];
    }

    private function auditState(User $user): array
    {
        return [
            'name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile,
            'role' => $user->role->value, 'branch_id' => $user->branch_id, 'is_active' => $user->is_active,
            'lead_visibility' => $user->lead_visibility,
            'campaigns' => $user->campaigns()->orderBy('name')->pluck('name')->all(),
            'permissions' => $user->permissions()->orderBy('permission')->pluck('permission')->all(),
        ];
    }
}
