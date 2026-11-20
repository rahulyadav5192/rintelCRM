<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use App\Services\LeadFilters;
use App\Services\LeadStats;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $filters = array_intersect_key(LeadFilters::fromRequest($request), array_flip(['date_from', 'date_to', 'branch_id', 'campaign_id']));
        if (! $user->isSuperAdmin()) {
            unset($filters['branch_id']);
        }
        $stats = new LeadStats($user, $filters);
        $today = now()->toDateString();

        $to = LeadFilters::date($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to']) : now();
        $from = LeadFilters::date($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from']) : $to->copy()->subDays(29);
        if ($from->diffInDays($to) > 92) {
            $from = $to->copy()->subDays(92);
        }

        $cards = [
            'total' => $stats->base()->count(),
            'today' => $stats->base()->whereDate('leads.lead_date', $today)->count(),
            'month' => $stats->base()->whereBetween('leads.lead_date', [now()->startOfMonth()->toDateString(), $today])->count(),
        ];

        if ($user->isSuperAdmin()) {
            $cards += [
                'branches' => Branch::count(),
                'active_branches' => Branch::active()->count(),
                'campaigns' => Campaign::count(),
                'active_campaigns' => Campaign::active()->count(),
                'users' => User::count(),
                'active_managers' => User::active()->where('role', Role::Manager->value)->count(),
            ];
        } elseif ($user->isManager()) {
            $cards += [
                'assigned' => $stats->base()->where('leads.assigned_to', $user->id)->count(),
                'follow_ups' => $stats->base()->where('leads.assigned_to', $user->id)->whereNotNull('leads.follow_up_date')->whereDate('leads.follow_up_date', '<=', $today)->count(),
            ];
        } else {
            $cards += [
                'unassigned' => $stats->base()->whereNull('leads.assigned_to')->count(),
                'managers' => User::active()->where('role', Role::Manager->value)->where('branch_id', $user->branch_id)->count(),
            ];
        }

        $charts = [
            'byCampaign' => $stats->byCampaign(),
            'byStatus' => $stats->byStatus(),
            'byPaymentStatus' => $stats->byPaymentStatus(),
            'daily' => $stats->daily($from, $to),
        ];
        if ($user->isSuperAdmin()) {
            $charts['byBranch'] = $stats->byBranch();
            $charts['monthly'] = $stats->monthly();
        } else {
            $charts['byManager'] = $stats->byManager();
        }

        $recentActivity = LeadActivity::query()
            ->whereIn('lead_id', Lead::query()->visibleTo($user)->select('leads.id'))
            ->with(['user:id,name', 'lead:id,lead_number,first_name,last_name'])
            ->latest('created_at')->latest('id')->limit(8)->get()
            ->map(fn ($a) => [
                'id' => $a->id, 'type' => $a->type, 'description' => $a->description, 'user' => $a->user?->name ?? 'System',
                'lead_id' => $a->lead_id, 'lead_number' => $a->lead?->lead_number,
                'lead_name' => trim(($a->lead?->first_name ?? '').' '.($a->lead?->last_name ?? '')),
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        $followUps = $user->isManager()
            ? Lead::query()->visibleTo($user)->where('assigned_to', $user->id)->whereNotNull('follow_up_date')->whereDate('follow_up_date', '<=', now()->addDays(7))
                ->with(['campaign:id,name', 'leadStatus:id,name,color'])->orderBy('follow_up_date')->limit(8)->get()
                ->map(fn ($l) => ['id' => $l->id, 'lead_number' => $l->lead_number, 'name' => $l->full_name, 'campaign' => $l->campaign?->name, 'follow_up_date' => $l->follow_up_date?->format('Y-m-d'), 'lead_status' => $l->leadStatus?->only('name', 'color'), 'overdue' => $l->follow_up_date?->lt(now()->startOfDay())])
            : [];

        return Inertia::render('Dashboard', [
            'role' => $user->role->value,
            'cards' => $cards,
            'charts' => $charts,
            'recentActivity' => $recentActivity,
            'followUps' => $followUps,
            'filters' => $filters,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'filterOptions' => [
                'branches' => $user->isSuperAdmin() ? Branch::orderBy('name')->get(['id', 'name']) : [],
                'campaigns' => Campaign::query()->accessibleBy($user)->orderBy('name')->get(['campaigns.id', 'campaigns.name']),
            ],
        ]);
    }
}
