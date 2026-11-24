<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignLeadStatus;
use App\Models\CampaignPaymentStatus;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-campaign status management. Statuses remain campaign-specific rows (so each campaign can
 * still diverge); this screen creates and edits same-named statuses in many campaigns at once.
 */
class StatusManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('manage-statuses');
        $type = $request->query('type') === 'payment' ? 'payment' : 'lead';
        $model = $this->model($type);

        $campaigns = Campaign::query()->orderBy('name')->get(['id', 'name', 'code', 'is_active']);
        $statuses = $model::query()->withCount('leads')->with('campaign:id,name')->orderBy('sort_order')->get();

        // Group rows that share a name (case-insensitive) across campaigns.
        $groups = $statuses->groupBy(fn ($s) => mb_strtolower($s->name))->map(function ($rows) {
            $first = $rows->first();

            return [
                'key' => mb_strtolower($first->name),
                'name' => $first->name,
                'color' => $rows->countBy('color')->sortDesc()->keys()->first(),
                'leads_count' => $rows->sum('leads_count'),
                'campaigns' => $rows->sortBy(fn ($r) => $r->campaign?->name)->values()->map(fn ($r) => [
                    'status_id' => $r->id,
                    'campaign_id' => $r->campaign_id,
                    'campaign' => $r->campaign?->name,
                    'is_active' => $r->is_active,
                    'is_default' => $r->is_default,
                    'leads_count' => $r->leads_count,
                ]),
            ];
        })->sortBy(fn ($g) => $statuses->firstWhere(fn ($s) => mb_strtolower($s->name) === $g['key'])?->sort_order)->values();

        return Inertia::render('Statuses/Index', [
            'type' => $type,
            'groups' => $groups,
            'campaigns' => $campaigns,
            'colors' => CampaignStatusController::COLORS,
        ]);
    }

    /** Creates a status (or adds an existing one) in every selected campaign that does not have it yet. */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-statuses');
        $data = $request->validate([
            'type' => ['required', Rule::in(['lead', 'payment'])],
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(CampaignStatusController::COLORS)],
            'is_default' => ['boolean'],
            'campaign_ids' => ['required', 'array', 'min:1'],
            'campaign_ids.*' => ['integer', 'exists:campaigns,id'],
        ], ['campaign_ids.required' => 'Select at least one campaign.', 'campaign_ids.min' => 'Select at least one campaign.']);
        $model = $this->model($data['type']);
        $name = trim($data['name']);

        [$created, $skipped] = DB::transaction(function () use ($data, $model, $name) {
            $created = [];
            $skipped = [];
            foreach (Campaign::query()->whereIn('id', $data['campaign_ids'])->orderBy('name')->get() as $campaign) {
                if ($model::where('campaign_id', $campaign->id)->where('name', $name)->exists()) {
                    $skipped[] = $campaign->name;

                    continue;
                }
                $status = new $model([
                    'name' => $name,
                    'color' => $data['color'],
                    'sort_order' => ((int) $model::where('campaign_id', $campaign->id)->max('sort_order')) + 1,
                    'is_default' => (bool) ($data['is_default'] ?? false),
                    'is_active' => true,
                ]);
                $status->campaign_id = $campaign->id;
                $status->save();
                if ($status->is_default) {
                    $model::where('campaign_id', $campaign->id)->whereKeyNot($status->id)->update(['is_default' => false]);
                }
                $created[] = $campaign->name;
            }

            return [$created, $skipped];
        });

        if ($created) {
            ActivityLogger::log('campaigns', 'campaign.statuses_changed', "{$data['type']} status \"{$name}\" added to ".count($created).' campaign(s)', null, null, [
                'status' => $name, 'type' => $data['type'], 'campaigns' => $created, 'default' => (bool) ($data['is_default'] ?? false),
            ]);
        }

        $message = $created ? "\"{$name}\" added to ".implode(', ', $created).'.' : "\"{$name}\" already exists in every selected campaign.";
        if ($created && $skipped) {
            $message .= ' Already present in '.implode(', ', $skipped).'.';
        }

        return back()->with($created ? 'success' : 'error', $message);
    }

    /** Renames / recolours / (de)activates a same-named status in the chosen campaigns. */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manage-statuses');
        $data = $request->validate([
            'type' => ['required', Rule::in(['lead', 'payment'])],
            'status_ids' => ['required', 'array', 'min:1'],
            'status_ids.*' => ['integer'],
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', Rule::in(CampaignStatusController::COLORS)],
            'is_active' => ['required', 'boolean'],
        ]);
        $model = $this->model($data['type']);
        $name = trim($data['name']);
        $rows = $model::query()->whereIn('id', $data['status_ids'])->with('campaign:id,name')->get();

        // A rename must not collide with another status in any of the affected campaigns.
        $conflicts = $rows->filter(fn ($r) => $model::where('campaign_id', $r->campaign_id)->where('name', $name)->whereKeyNot($r->id)->exists());
        if ($conflicts->isNotEmpty()) {
            return back()->withErrors(['name' => "A status called \"{$name}\" already exists in ".$conflicts->pluck('campaign.name')->implode(', ').'.']);
        }

        $skippedDefaults = [];
        DB::transaction(function () use ($rows, $name, $data, &$skippedDefaults) {
            foreach ($rows as $row) {
                $row->name = $name;
                $row->color = $data['color'];
                if (! $data['is_active'] && $row->is_default) {
                    $skippedDefaults[] = $row->campaign?->name; // default statuses stay active
                } else {
                    $row->is_active = $data['is_active'];
                }
                $row->save();
            }
        });

        ActivityLogger::log('campaigns', 'campaign.statuses_changed', "{$data['type']} status \"{$name}\" updated in {$rows->count()} campaign(s)", null, null, [
            'status' => $name, 'color' => $data['color'], 'is_active' => $data['is_active'], 'campaigns' => $rows->pluck('campaign.name')->all(),
        ]);

        $message = 'Status updated in '.$rows->count().' campaign(s).';
        if ($skippedDefaults) {
            $message .= ' Kept active where it is the default: '.implode(', ', $skippedDefaults).'.';
        }

        return back()->with('success', $message);
    }

    /** @return class-string<CampaignLeadStatus|CampaignPaymentStatus> */
    private function model(string $type): string
    {
        return $type === 'payment' ? CampaignPaymentStatus::class : CampaignLeadStatus::class;
    }
}
