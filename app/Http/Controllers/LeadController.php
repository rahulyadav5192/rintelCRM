<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Requests\LeadRequest;
use App\Models\Campaign;
use App\Models\CampaignLeadStatus;
use App\Models\CampaignPaymentStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LeadFilters;
use App\Services\LeadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function __construct(private readonly LeadService $leads) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = LeadFilters::fromRequest($request);

        $leads = LeadFilters::applySort(LeadFilters::query($user, $filters), $filters)
            ->with(['admin:id,name', 'campaign:id,name,code', 'assignee:id,name', 'creator:id,name', 'leadStatus:id,name,color', 'paymentStatus:id,name,color'])
            ->paginate(min(max((int) $request->query('per_page', 15), 10), 100))
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'lead_number' => $lead->lead_number,
                'lead_date' => $lead->lead_date?->format('Y-m-d'),
                'name' => $lead->full_name,
                'mobile' => $lead->mobile,
                'email' => $lead->email,
                'admin' => $lead->admin?->name,
                'campaign' => $lead->campaign?->name,
                'assignee' => $lead->assignee?->name,
                'lead_status' => $lead->leadStatus?->only('name', 'color'),
                'payment_status' => $lead->paymentStatus?->only('name', 'color'),
                'creator' => $lead->creator?->name ?? ($lead->source === 'public' ? 'Public form' : null),
                'can' => [
                    'update' => $user->can('update', $lead),
                    'delete' => $user->can('delete', $lead),
                ],
            ]);

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($user),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Lead::class);
        $user = $request->user();

        return Inertia::render('Leads/Create', [
            ...$this->formOptions($user),
            'previewLeadNumber' => $this->leads->previewLeadNumber(),
            'defaults' => [
                'lead_date' => now()->toDateString(),
                'campaign_id' => $request->integer('campaign_id') ?: null,
            ],
        ]);
    }

    public function store(LeadRequest $request): RedirectResponse
    {
        $this->authorize('create', Lead::class);
        $lead = $this->leads->create($request->validated(), $request->user());

        return redirect()->route('leads.show', $lead)->with('success', "Lead {$lead->lead_number} created.");
    }

    public function show(Request $request, Lead $lead): Response
    {
        $this->authorize('view', $lead);
        $user = $request->user();
        $lead->load([
            'admin:id,name', 'campaign:id,name,code', 'assignee:id,name,email', 'creator:id,name',
            'leadStatus:id,name,color', 'paymentStatus:id,name,color',
            'activities.user:id,name',
        ]);
        $updatedBy = $lead->updated_by ? User::withTrashed()->find($lead->updated_by, ['id', 'name']) : null;

        return Inertia::render('Leads/Show', [
            'lead' => [
                ...$this->leadFormData($lead),
                'full_name' => $lead->full_name,
                'source' => $lead->source,
                'admin' => $lead->admin?->name,
                'campaign' => $lead->campaign?->only('name', 'code'),
                'assignee' => $lead->assignee?->only('name', 'email'),
                'creator' => $lead->creator?->name ?? ($lead->source === 'public' ? 'Public form' : null),
                'updated_by_name' => $updatedBy?->name,
                'lead_status' => $lead->leadStatus?->only('name', 'color'),
                'payment_status' => $lead->paymentStatus?->only('name', 'color'),
                'created_at' => $lead->created_at?->toIso8601String(),
                'updated_at' => $lead->updated_at?->toIso8601String(),
                'activities' => $lead->activities->map(fn ($a) => [
                    'id' => $a->id, 'type' => $a->type, 'description' => $a->description, 'user' => $a->user?->name ?? 'System',
                    'old_values' => $a->old_values, 'new_values' => $a->new_values, 'created_at' => $a->created_at?->toIso8601String(),
                ]),
            ],
            'can' => [
                'update' => $user->can('update', $lead),
                'delete' => $user->can('delete', $lead),
                'addNote' => $user->can('addNote', $lead),
            ],
        ]);
    }

    public function edit(Request $request, Lead $lead): Response
    {
        $this->authorize('update', $lead);
        $user = $request->user();
        $lead->load(['campaign:id,name', 'admin:id,name']);

        $options = $this->formOptions($user);
        // Keep the lead's current (possibly now inactive) campaign selectable so the form renders.
        if (! collect($options['campaigns'])->contains('id', $lead->campaign_id)) {
            $options['campaigns'][] = ['id' => $lead->campaign_id, 'name' => $lead->campaign?->name];
        }

        $lead->load(['leadStatus:id,name', 'paymentStatus:id,name', 'assignee:id,name']);

        return Inertia::render('Leads/Edit', [
            ...$options,
            'lead' => $this->leadFormData($lead),
            'currentOptions' => [
                'lead_status' => $lead->leadStatus?->only('id', 'name'),
                'payment_status' => $lead->paymentStatus?->only('id', 'name'),
                'assignee' => $lead->assignee?->only('id', 'name'),
                'admin' => $lead->admin?->only('id', 'name'),
            ],
            'abilities' => [
                'editDetails' => $user->hasPermission(Permission::EditLeads),
                // Admins can only view a lead's campaign, assignment and status.
                'changeCampaign' => $user->hasPermission(Permission::EditLeads) && ! $user->isAdmin(),
                'changeOwner' => $user->isSuperAdmin(),
                'assign' => $user->hasPermission(Permission::AssignLeads),
                'updateStatus' => $user->hasPermission(Permission::UpdateLeadStatus),
                'updatePaymentStatus' => $user->hasPermission(Permission::UpdatePaymentStatus),
            ],
        ]);
    }

    public function update(LeadRequest $request, Lead $lead): RedirectResponse
    {
        $this->authorize('update', $lead);
        $this->leads->update($lead, $request->validated(), $request->user());

        return redirect()->route('leads.show', $lead)->with('success', 'Lead updated.');
    }

    public function destroy(Request $request, Lead $lead): RedirectResponse
    {
        $this->authorize('delete', $lead);
        $lead->delete();
        ActivityLogger::log('leads', 'lead.deleted', "Lead {$lead->lead_number} deleted", $lead->id);

        return redirect()->route('leads.index')->with('success', "Lead {$lead->lead_number} deleted.");
    }

    // ---- Helpers ------------------------------------------------------------------------

    private function leadFormData(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'lead_number' => $lead->lead_number,
            'lead_date' => $lead->lead_date?->format('Y-m-d'),
            'title' => $lead->title,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'mobile' => $lead->mobile,
            'email' => $lead->email,
            'house_number' => $lead->house_number,
            'post_code' => $lead->post_code,
            'admin_id' => $lead->admin_id,
            'campaign_id' => $lead->campaign_id,
            'assigned_to' => $lead->assigned_to,
            'lead_status_id' => $lead->lead_status_id,
            'payment_status_id' => $lead->payment_status_id,
            'follow_up_date' => $lead->follow_up_date?->format('Y-m-d'),
            'notes' => $lead->notes,
        ];
    }

    /** Campaigns the user may create/assign leads in, plus what they may choose on the form. */
    private function formOptions(User $user): array
    {
        return [
            'campaigns' => $this->leads->creatableCampaigns($user)->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'titles' => Lead::TITLES,
            'canChooseOwner' => $this->leads->canChooseOwner($user),
            'canSetDate' => $user->isSuperAdmin() || ($user->isAdmin() && $user->hasPermission(Permission::EditLeads)),
            'canAssign' => $user->hasPermission(Permission::AssignLeads),
            'currentUserId' => $user->id,
        ];
    }

    private function filterOptions(User $user): array
    {
        $campaignIds = $user->isSuperAdmin() ? null : $user->campaignIds();

        $managers = User::query()->withTrashed()->where('role', Role::Manager->value)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereHas('campaigns', fn ($c) => $c->whereIn('campaigns.id', $campaignIds ?: [0])))
            ->orderBy('name')->get(['id', 'name']);

        $statusQuery = fn (string $model) => $model::query()
            ->when($campaignIds !== null, fn ($q) => $q->whereIn('campaign_id', $campaignIds ?: [0]))
            ->orderBy('campaign_id')->orderBy('sort_order')->get(['id', 'name', 'campaign_id']);

        return [
            'admins' => $user->isSuperAdmin() ? User::withTrashed()->where('role', Role::Admin->value)->orderBy('name')->get(['id', 'name']) : [],
            'campaigns' => Campaign::query()->accessibleBy($user)->orderBy('name')->get(['campaigns.id', 'campaigns.name']),
            'managers' => $managers,
            'leadStatuses' => $statusQuery(CampaignLeadStatus::class),
            'paymentStatuses' => $statusQuery(CampaignPaymentStatus::class),
            'showAdminFilter' => $user->isSuperAdmin(),
        ];
    }
}
