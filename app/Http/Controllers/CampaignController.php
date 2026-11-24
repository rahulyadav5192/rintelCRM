<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /** Sample defaults applied to new campaigns; Super Admins edit them freely afterwards. */
    private const DEFAULT_LEAD_STATUSES = [['New', 'blue'], ['Contacted', 'amber'], ['Completed', 'green'], ['Rejected', 'red']];

    private const DEFAULT_PAYMENT_STATUSES = [['Pending', 'amber'], ['Paid', 'green'], ['Failed', 'red']];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Campaign::class);
        $user = $request->user();
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $campaigns = Campaign::query()->accessibleBy($user)
            ->withCount([
                'leads' => fn ($q) => $q->visibleTo($user),
                'managers',
                'admins',
            ])
            ->with('admins:id,name')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)->withQueryString()
            ->through(fn (Campaign $c) => [
                ...$c->only('id', 'name', 'code', 'description', 'is_active', 'is_public', 'leads_count', 'managers_count', 'admins_count'),
                'admins' => $user->isSuperAdmin() ? $c->admins->pluck('name') : [],
            ]);

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => $request->only('search', 'status'),
            'canManage' => $user->can('create', Campaign::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Campaign::class);

        return Inertia::render('Campaigns/Create', $this->formOptions());
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $this->authorize('create', Campaign::class);
        $data = $request->validated();

        $campaign = DB::transaction(function () use ($data) {
            $campaign = new Campaign($data);
            $campaign->is_active = $data['is_active'] ?? true;
            $campaign->save();
            $this->syncRoleUsers($campaign, Role::Admin, $data['admin_ids'] ?? []);
            $this->syncRoleUsers($campaign, Role::Manager, $data['manager_ids'] ?? []);
            foreach (self::DEFAULT_LEAD_STATUSES as $i => [$name, $color]) {
                $campaign->leadStatuses()->create(['name' => $name, 'color' => $color, 'sort_order' => $i + 1, 'is_default' => $i === 0]);
            }
            foreach (self::DEFAULT_PAYMENT_STATUSES as $i => [$name, $color]) {
                $campaign->paymentStatuses()->create(['name' => $name, 'color' => $color, 'sort_order' => $i + 1, 'is_default' => $i === 0]);
            }

            return $campaign;
        });

        ActivityLogger::log('campaigns', 'campaign.created', "Campaign {$campaign->name} created", $campaign->id, null, $campaign->only('name', 'code', 'is_active', 'is_public'));

        return redirect()->route('campaigns.statuses', $campaign)->with('success', 'Campaign created with default statuses — customise them below.');
    }

    public function show(Request $request, Campaign $campaign): Response
    {
        $this->authorize('view', $campaign);
        $user = $request->user();
        $campaign->load(['leadStatuses', 'paymentStatuses']);

        // Admins only see the managers working the campaign (plus themselves), not other admins.
        $usersQuery = $campaign->users()->orderBy('role')->orderBy('name');
        if (! $user->isSuperAdmin()) {
            $usersQuery->where(fn ($q) => $q->where('users.role', Role::Manager->value)->orWhere('users.id', $user->id));
        }

        $leadBase = fn () => Lead::query()->visibleTo($user)->where('leads.campaign_id', $campaign->id);
        $statusCounts = $leadBase()->selectRaw('lead_status_id, count(*) as total')->groupBy('lead_status_id')->pluck('total', 'lead_status_id');

        return Inertia::render('Campaigns/Show', [
            'campaign' => [
                ...$campaign->only('id', 'name', 'code', 'description', 'is_active', 'is_public', 'public_show_statuses'),
                'created_at' => $campaign->created_at?->toIso8601String(),
                'lead_statuses' => $campaign->leadStatuses->map(fn ($s) => [...$s->only('id', 'name', 'color', 'is_active', 'is_default'), 'leads_count' => (int) ($statusCounts[$s->id] ?? 0)]),
                'payment_statuses' => $campaign->paymentStatuses->map->only('id', 'name', 'color', 'is_active', 'is_default'),
                'leads_count' => $leadBase()->count(),
            ],
            'users' => $usersQuery->get()->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role->label(), 'is_active' => $u->is_active,
            ]),
            'canManage' => $user->can('update', $campaign),
        ]);
    }

    public function edit(Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);

        return Inertia::render('Campaigns/Edit', [
            ...$this->formOptions(),
            'campaign' => [
                ...$campaign->only('id', 'name', 'code', 'description', 'is_active', 'is_public', 'public_show_statuses'),
                'admin_ids' => $campaign->admins()->pluck('users.id'),
                'manager_ids' => $campaign->managers()->pluck('users.id'),
            ],
        ]);
    }

    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $data = $request->validated();
        $fields = ['name', 'code', 'description', 'is_active', 'is_public', 'public_show_statuses'];
        $before = [...$campaign->only($fields), 'admin_ids' => $campaign->admins()->pluck('users.id')->sort()->values()->all(), 'manager_ids' => $campaign->managers()->pluck('users.id')->sort()->values()->all()];

        DB::transaction(function () use ($campaign, $data) {
            $campaign->fill($data);
            $campaign->is_active = $data['is_active'] ?? $campaign->is_active;
            $campaign->save();
            $this->syncRoleUsers($campaign, Role::Admin, $data['admin_ids'] ?? []);
            $this->syncRoleUsers($campaign, Role::Manager, $data['manager_ids'] ?? []);
        });

        $after = [...$campaign->only($fields), 'admin_ids' => $campaign->admins()->pluck('users.id')->sort()->values()->all(), 'manager_ids' => $campaign->managers()->pluck('users.id')->sort()->values()->all()];
        [$old, $new] = ActivityLogger::diff($before, $after);
        if ($new) {
            ActivityLogger::log('campaigns', 'campaign.updated', "Campaign {$campaign->name} updated", $campaign->id, $old, $new);
        }

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign updated.');
    }

    public function toggle(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $campaign->is_active = ! $campaign->is_active;
        $campaign->save();
        $state = $campaign->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log('campaigns', "campaign.{$state}", "Campaign {$campaign->name} {$state}", $campaign->id, ['is_active' => ! $campaign->is_active], ['is_active' => $campaign->is_active]);

        return back()->with('success', "Campaign {$state}.");
    }

    /** Syncs the campaign's users of one role (admins or managers), leaving the other role untouched. */
    private function syncRoleUsers(Campaign $campaign, Role $role, array $ids): void
    {
        $ids = User::query()->where('role', $role->value)->whereIn('id', $ids)->pluck('id')->all();
        $current = $campaign->users()->where('role', $role->value)->pluck('users.id')->all();
        $campaign->users()->detach(array_diff($current, $ids));
        $campaign->users()->syncWithoutDetaching($ids);
    }

    private function formOptions(): array
    {
        return [
            'admins' => User::query()->where('role', Role::Admin->value)->orderBy('name')->get(['id', 'name', 'email', 'is_active']),
            'managers' => User::query()->where('role', Role::Manager->value)->orderBy('name')->get(['id', 'name', 'email', 'is_active']),
        ];
    }
}
