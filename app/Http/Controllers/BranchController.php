<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Branch::class);
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $branches = Branch::query()
            ->withCount(['users', 'leads', 'campaigns'])
            ->with('publicForm:id,branch_id,is_active')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('contact_person', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'inactive'], true), fn ($q) => $q->where('is_active', $status === 'active'))
            ->orderBy(in_array($request->query('sort'), ['name', 'code', 'created_at'], true) ? $request->query('sort') : 'name', $request->query('direction') === 'desc' ? 'desc' : 'asc')
            ->paginate(15)->withQueryString();

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'filters' => $request->only('search', 'status', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Branch::class);

        return Inertia::render('Branches/Create', ['campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name', 'is_active'])]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $this->authorize('create', Branch::class);
        $data = $request->validated();

        $branch = DB::transaction(function () use ($data) {
            $branch = new Branch($data);
            $branch->is_active = $data['is_active'] ?? true;
            $branch->save();
            $branch->campaigns()->sync($data['campaign_ids'] ?? []);

            return $branch;
        });

        ActivityLogger::log('branches', 'branch.created', "Branch {$branch->name} created", $branch->id, null, $branch->only('name', 'code', 'is_active'));

        return redirect()->route('branches.show', $branch)->with('success', 'Branch created.');
    }

    public function show(Request $request, Branch $branch): Response
    {
        $this->authorize('view', $branch);
        $branch->load(['campaigns:id,name,code,is_active', 'publicForm']);
        $branch->loadCount(['users', 'leads']);

        $users = $branch->users()->with('campaigns:id,name')->orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active', 'branch_id']);
        $recentLeads = Lead::query()->where('branch_id', $branch->id)->with(['campaign:id,name', 'assignee:id,name', 'leadStatus:id,name,color'])->latest('id')->limit(10)->get();

        return Inertia::render('Branches/Show', [
            'branch' => [
                ...$branch->only('id', 'name', 'code', 'address', 'contact_person', 'contact_number', 'email', 'is_active', 'users_count', 'leads_count'),
                'created_at' => $branch->created_at?->toIso8601String(),
                'campaigns' => $branch->campaigns->map(fn ($c) => [
                    ...$c->only('id', 'name', 'code', 'is_active'),
                    'leads_count' => Lead::query()->where('branch_id', $branch->id)->where('campaign_id', $c->id)->count(),
                ]),
                'public_form' => $branch->publicForm?->only('id', 'is_active', 'submissions_count'),
            ],
            'users' => $users->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->role->label(), 'is_active' => $u->is_active,
                'campaigns' => $u->campaigns->pluck('name'),
            ]),
            'recentLeads' => $recentLeads->map(fn ($l) => [
                'id' => $l->id, 'lead_number' => $l->lead_number, 'name' => $l->full_name, 'campaign' => $l->campaign?->name,
                'assignee' => $l->assignee?->name, 'lead_status' => $l->leadStatus?->only('name', 'color'), 'lead_date' => $l->lead_date?->format('Y-m-d'),
            ]),
        ]);
    }

    public function edit(Branch $branch): Response
    {
        $this->authorize('update', $branch);

        return Inertia::render('Branches/Edit', [
            'branch' => [...$branch->only('id', 'name', 'code', 'address', 'contact_person', 'contact_number', 'email', 'is_active'), 'campaign_ids' => $branch->campaigns()->pluck('campaigns.id')],
            'campaigns' => Campaign::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);
        $data = $request->validated();
        $before = [...$branch->only('name', 'code', 'address', 'contact_person', 'contact_number', 'email', 'is_active'), 'campaign_ids' => $branch->campaigns()->pluck('campaigns.id')->sort()->values()->all()];

        DB::transaction(function () use ($branch, $data) {
            $branch->fill($data);
            $branch->is_active = $data['is_active'] ?? $branch->is_active;
            $branch->save();
            $branch->campaigns()->sync($data['campaign_ids'] ?? []);
        });

        $after = [...$branch->only('name', 'code', 'address', 'contact_person', 'contact_number', 'email', 'is_active'), 'campaign_ids' => collect($data['campaign_ids'] ?? [])->map(fn ($v) => (int) $v)->sort()->values()->all()];
        [$old, $new] = ActivityLogger::diff($before, $after);
        if ($new) {
            ActivityLogger::log('branches', 'branch.updated', "Branch {$branch->name} updated", $branch->id, $old, $new);
        }

        return redirect()->route('branches.show', $branch)->with('success', 'Branch updated.');
    }

    public function toggle(Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);
        $branch->is_active = ! $branch->is_active;
        $branch->save();
        $state = $branch->is_active ? 'activated' : 'deactivated';
        ActivityLogger::log('branches', "branch.{$state}", "Branch {$branch->name} {$state}", $branch->id, ['is_active' => ! $branch->is_active], ['is_active' => $branch->is_active]);

        return back()->with('success', "Branch {$state}.");
    }
}
