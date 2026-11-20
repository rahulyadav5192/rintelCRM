<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\CampaignRequest;
use App\Models\Branch;
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
                'branches',
            ])
            ->with('branches:id,name')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(15)->withQueryString()
            ->through(fn (Campaign $c) => [
                ...$c->only('id', 'name', 'code', 'description', 'is_active', 'is_public', 'leads_count', 'managers_count', 'branches_count'),
                'branches' => $c->branches->pluck('name'),
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
            $campaign->branches()->sync($data['branch_ids'] ?? []);
            $this->syncManagers($campaign, $data['manager_ids'] ?? []);
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
        $campaign->load(['branches:id,name,is_active', 'leadStatuses', 'paymentStatuses']);

        $usersQuery = $campaign->users()->with('branch:id,name')->orderBy('name');
        if (! $user->isSuperAdmin()) {
            $usersQuery->where('users.branch_id', $user->branch_id);
        }

        $leadBase = fn () => Lead::query()->visibleTo($user)->where('leads.campaign_id', $campaign->id);
        $statusCounts = $leadBase()->selectRaw('lead_status_id, count(*) as total')->groupBy('lead_status_id')->pluck('total', 'lead_status_id');

        return Inertia::render('Campaigns/Show', [
            'campaign' => [
                ...$campaign->only('id', 'name', 'code', 'description', 'is_active', 'is_public', 'public_show_statuses'),
                'created_at' => $campaign->created_at?->toIso8601String(),
                'branches' => $campaign->branches->when(! $user->isSuperAdmin(), fn ($c) => $c->where('id', $user->branch_id))->values()->map->only('id', 'name', 'is_active'),
                'lead_statuses' => $campaign->leadStatuses->map(fn ($s) => [...$s->only('id', 'name', 'color', 'is_active', 'is_default'), 'leads_count' => (int) ($statusCounts[$s->id] ?? 0)]),
                'payment_statuses' => $campaign->paymentStatuses->map->only('id', 'name', 'color', 'is_active', 'is_default'),
                'leads_count' => $leadBase()->count(),
            ],
            'users' => $usersQuery->get()->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role->label(), 'branch' => $u->branch?->name, 'is_active' => $u->is_active,
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
                'branch_ids' => $campaign->branches()->pluck('branches.id'),
                'manager_ids' => $campaign->managers()->pluck('users.id'),
            ],
        ]);
    }

    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $data = $request->validated();
        $fields = ['name', 'code', 'description', 'is_active', 'is_public', 'public_show_statuses'];
        $before = [...$campaign->only($fields), 'branch_ids' => $campaign->branches()->pluck('branches.id')->sort()->values()->all(), 'manager_ids' => $campaign->managers()->pluck('users.id')->sort()->values()->all()];

        DB::transaction(function () use ($campaign, $data) {
            $campaign->fill($data);
            $campaign->is_active = $data['is_active'] ?? $campaign->is_active;
            $campaign->save();
            $campaign->branches()->sync($data['branch_ids'] ?? []);
            $this->syncManagers($campaign, $data['manager_ids'] ?? []);
        });

        $after = [...$campaign->only($fields), 'branch_ids' => $campaign->branches()->pluck('branches.id')->sort()->values()->all(), 'manager_ids' => $campaign->managers()->pluck('users.id')->sort()->values()->all()];
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

    /** Only the manager links are synced here; Branch Admin campaign access is managed on the user. */
    private function syncManagers(Campaign $campaign, array $managerIds): void
    {
        $managerIds = User::query()->where('role', Role::Manager->value)->whereIn('id', $managerIds)->pluck('id')->all();
        $current = $campaign->managers()->pluck('users.id')->all();
        $campaign->users()->detach(array_diff($current, $managerIds));
        $campaign->users()->syncWithoutDetaching($managerIds);
    }

    private function formOptions(): array
    {
        return [
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'managers' => User::query()->where('role', Role::Manager->value)->with('branch:id,name')->orderBy('name')->get(['id', 'name', 'branch_id', 'is_active'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'branch_id' => $u->branch_id, 'branch' => $u->branch?->name, 'is_active' => $u->is_active]),
        ];
    }
}
