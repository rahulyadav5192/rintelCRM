<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aggregations for dashboards and reports. Every method takes a base query that already went
 * through Lead::visibleTo() (via LeadFilters::query), so statistics never leak other data.
 */
class LeadStats
{
    public function __construct(private readonly User $user, private readonly array $filters) {}

    public function base(): Builder
    {
        return LeadFilters::query($this->user, $this->filters);
    }

    public function byCampaign(): array
    {
        return $this->base()->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->groupBy('campaigns.id', 'campaigns.name')->orderByDesc('total')
            ->get(['campaigns.id', 'campaigns.name', DB::raw('count(*) as total')])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'total' => (int) $r->total])->all();
    }

    public function byAdmin(): array
    {
        return $this->base()->leftJoin('users as owner', 'owner.id', '=', 'leads.admin_id')
            ->groupBy('owner.id', 'owner.name')->orderByDesc('total')
            ->get(['owner.id', 'owner.name', DB::raw('count(*) as total')])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name ?? 'No admin', 'total' => (int) $r->total])->all();
    }

    public function byManager(): array
    {
        return $this->base()->leftJoin('users', 'users.id', '=', 'leads.assigned_to')
            ->groupBy('users.id', 'users.name')->orderByDesc('total')
            ->get(['users.id', 'users.name', DB::raw('count(*) as total')])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name ?? 'Unassigned', 'total' => (int) $r->total])->all();
    }

    /** Statuses are campaign-specific; across campaigns they are merged by name. */
    public function byStatus(): array
    {
        return $this->base()->join('campaign_lead_statuses as s', 's.id', '=', 'leads.lead_status_id')
            ->groupBy('s.name')->orderByDesc('total')
            ->get(['s.name', DB::raw('min(s.color) as color'), DB::raw('count(*) as total')])
            ->map(fn ($r) => ['name' => $r->name, 'color' => $r->color, 'total' => (int) $r->total])->all();
    }

    public function byPaymentStatus(): array
    {
        return $this->base()->leftJoin('campaign_payment_statuses as p', 'p.id', '=', 'leads.payment_status_id')
            ->groupBy('p.name')->orderByDesc('total')
            ->get(['p.name', DB::raw('min(p.color) as color'), DB::raw('count(*) as total')])
            ->map(fn ($r) => ['name' => $r->name ?? 'Not set', 'color' => $r->color ?? 'gray', 'total' => (int) $r->total])->all();
    }

    public function daily(Carbon $from, Carbon $to): array
    {
        $rows = $this->base()->whereBetween('leads.lead_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('leads.lead_date')->get([DB::raw('leads.lead_date as day'), DB::raw('count(*) as total')])
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->day)->toDateString() => (int) $r->total]);

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $out[] = ['date' => $d->toDateString(), 'label' => $d->format('d M'), 'total' => $rows[$d->toDateString()] ?? 0];
        }

        return $out;
    }

    public function monthly(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = $this->base()->where('leads.lead_date', '>=', $start->toDateString())
            ->groupBy('ym')->get([DB::raw("DATE_FORMAT(leads.lead_date, '%Y-%m') as ym"), DB::raw('count(*) as total')])
            ->pluck('total', 'ym');

        $out = [];
        for ($m = $start->copy(); $m->lte(now()); $m->addMonth()) {
            $out[] = ['month' => $m->format('Y-m'), 'label' => $m->format('M Y'), 'total' => (int) ($rows[$m->format('Y-m')] ?? 0)];
        }

        return $out;
    }
}
