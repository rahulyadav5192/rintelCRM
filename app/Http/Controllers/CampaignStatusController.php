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
 * Per-campaign lead/payment status configuration. Statuses are never hard-deleted (leads reference
 * them); they are deactivated instead, which hides them from forms while keeping history intact.
 */
class CampaignStatusController extends Controller
{
    public const COLORS = ['gray', 'blue', 'sky', 'indigo', 'violet', 'green', 'emerald', 'amber', 'orange', 'red', 'pink'];

    public function index(Campaign $campaign): Response
    {
        $this->authorize('update', $campaign);
        $campaign->load(['leadStatuses' => fn ($q) => $q->withCount('leads'), 'paymentStatuses' => fn ($q) => $q->withCount('leads')]);

        return Inertia::render('Campaigns/Statuses', [
            'campaign' => $campaign->only('id', 'name', 'code'),
            'leadStatuses' => $campaign->leadStatuses->map->only('id', 'name', 'color', 'sort_order', 'is_default', 'is_active', 'leads_count'),
            'paymentStatuses' => $campaign->paymentStatuses->map->only('id', 'name', 'color', 'sort_order', 'is_default', 'is_active', 'leads_count'),
            'colors' => self::COLORS,
        ]);
    }

    public function store(Request $request, Campaign $campaign, string $type): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $model = $this->model($type);
        $data = $this->validated($request, $campaign, $type);

        DB::transaction(function () use ($campaign, $model, $data) {
            $status = new $model([...$data, 'sort_order' => ((int) $model::where('campaign_id', $campaign->id)->max('sort_order')) + 1]);
            $status->campaign_id = $campaign->id;
            $status->is_active = true;
            $status->save();
            $this->ensureSingleDefault($model, $campaign, $data['is_default'] ?? false ? $status->id : null);
        });

        ActivityLogger::log('campaigns', 'campaign.statuses_changed', "{$campaign->name}: {$type} status \"{$data['name']}\" added", $campaign->id, null, $data);

        return back()->with('success', 'Status added.');
    }

    public function update(Request $request, Campaign $campaign, string $type, int $status): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $model = $this->model($type);
        $record = $model::where('campaign_id', $campaign->id)->findOrFail($status);
        $data = $this->validated($request, $campaign, $type, $record->id);
        $before = $record->only('name', 'color', 'is_default', 'is_active');

        if (($data['is_active'] ?? true) === false && ($data['is_default'] ?? false)) {
            return back()->withErrors(['is_active' => 'The default status cannot be deactivated.']);
        }

        DB::transaction(function () use ($record, $data, $model, $campaign) {
            $record->fill($data)->save();
            $this->ensureSingleDefault($model, $campaign, ($data['is_default'] ?? false) ? $record->id : null);
        });

        [$old, $new] = ActivityLogger::diff($before, $record->fresh()->only('name', 'color', 'is_default', 'is_active'));
        if ($new) {
            ActivityLogger::log('campaigns', 'campaign.statuses_changed', "{$campaign->name}: {$type} status \"{$record->name}\" updated", $campaign->id, $old, $new);
        }

        return back()->with('success', 'Status updated.');
    }

    public function reorder(Request $request, Campaign $campaign, string $type): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $model = $this->model($type);
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $owned = $model::where('campaign_id', $campaign->id)->pluck('id')->all();
        abort_if(array_diff($ids, $owned) || count($ids) !== count($owned), 422, 'Invalid status order.');

        DB::transaction(function () use ($ids, $model) {
            foreach ($ids as $i => $id) {
                $model::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });
        ActivityLogger::log('campaigns', 'campaign.statuses_changed', "{$campaign->name}: {$type} statuses reordered", $campaign->id, null, ['order' => $ids]);

        return back()->with('success', 'Order saved.');
    }

    private function validated(Request $request, Campaign $campaign, string $type, ?int $ignoreId = null): array
    {
        $table = $type === 'lead' ? 'campaign_lead_statuses' : 'campaign_payment_statuses';

        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique($table, 'name')->where('campaign_id', $campaign->id)->ignore($ignoreId)],
            'color' => ['required', Rule::in(self::COLORS)],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);
    }

    /** @return class-string<CampaignLeadStatus|CampaignPaymentStatus> */
    private function model(string $type): string
    {
        return $type === 'lead' ? CampaignLeadStatus::class : CampaignPaymentStatus::class;
    }

    private function ensureSingleDefault(string $model, Campaign $campaign, ?int $defaultId): void
    {
        if ($defaultId) {
            $model::where('campaign_id', $campaign->id)->whereKeyNot($defaultId)->update(['is_default' => false]);
            $model::whereKey($defaultId)->update(['is_active' => true]);
        }
    }
}
