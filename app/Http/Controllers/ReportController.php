<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\CampaignLeadStatus;
use App\Models\CampaignPaymentStatus;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LeadFilters;
use App\Services\LeadStats;
use App\Support\SpreadsheetExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public const TYPES = [
        'branch' => 'Branch-wise',
        'campaign' => 'Campaign-wise',
        'manager' => 'Manager-wise',
        'status' => 'Lead status',
        'payment' => 'Payment status',
        'date' => 'Date-wise',
    ];

    public function index(Request $request): Response|\Inertia\Response
    {
        $this->authorize('view-reports');
        $user = $request->user();
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : 'campaign';
        if ($type === 'branch' && ! $user->isSuperAdmin()) {
            $type = 'campaign';
        }
        $filters = array_intersect_key(LeadFilters::fromRequest($request), array_flip(['date_from', 'date_to', 'branch_id', 'campaign_id', 'assigned_to', 'lead_status_id', 'payment_status_id']));
        $stats = new LeadStats($user, $filters);

        $rows = match ($type) {
            'branch' => $stats->byBranch(),
            'campaign' => $stats->byCampaign(),
            'manager' => $stats->byManager(),
            'status' => $stats->byStatus(),
            'payment' => $stats->byPaymentStatus(),
            'date' => array_values(array_filter($stats->daily(...$this->dateRange($filters)), fn ($r) => $r['total'] > 0)),
        };
        $total = array_sum(array_column($rows, 'total'));

        if (in_array($request->query('export'), ['csv', 'xlsx'], true)) {
            $this->authorize('export', Lead::class);
            ActivityLogger::log('exports', 'report.exported', self::TYPES[$type].' report exported as '.strtoupper($request->query('export')), null, null, ['type' => $type, 'filters' => $filters]);

            return SpreadsheetExporter::download($request->query('export'), "report-{$type}", [self::TYPES[$type] === 'Date-wise' ? 'Date' : 'Name', 'Leads', 'Share %'],
                array_map(fn ($r) => [$r['name'] ?? $r['date'], $r['total'], $total ? round($r['total'] / $total * 100, 1) : 0], $rows));
        }

        $campaignIds = $user->isSuperAdmin() ? null : $user->campaignIds();

        return Inertia::render('Reports/Index', [
            'type' => $type,
            'types' => collect(self::TYPES)->when(! $user->isSuperAdmin(), fn ($c) => $c->except('branch'))->map(fn ($label, $key) => ['value' => $key, 'label' => $label])->values(),
            'rows' => $rows,
            'total' => $total,
            'filters' => $filters,
            'canExport' => $user->can('export', Lead::class),
            'filterOptions' => [
                'branches' => $user->isSuperAdmin() ? Branch::orderBy('name')->get(['id', 'name']) : [],
                'campaigns' => Campaign::query()->accessibleBy($user)->orderBy('name')->get(['campaigns.id', 'campaigns.name']),
                'managers' => User::query()->where('role', Role::Manager->value)
                    ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))->orderBy('name')->get(['id', 'name']),
                'leadStatuses' => CampaignLeadStatus::query()->when($campaignIds !== null, fn ($q) => $q->whereIn('campaign_id', $campaignIds ?: [0]))->orderBy('sort_order')->get(['id', 'name', 'campaign_id']),
                'paymentStatuses' => CampaignPaymentStatus::query()->when($campaignIds !== null, fn ($q) => $q->whereIn('campaign_id', $campaignIds ?: [0]))->orderBy('sort_order')->get(['id', 'name', 'campaign_id']),
            ],
        ]);
    }

    private function dateRange(array $filters): array
    {
        $to = LeadFilters::date($filters['date_to'] ?? null) ? Carbon::parse($filters['date_to']) : now();
        $from = LeadFilters::date($filters['date_from'] ?? null) ? Carbon::parse($filters['date_from']) : $to->copy()->subDays(29);
        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        return [$from, $to];
    }
}
