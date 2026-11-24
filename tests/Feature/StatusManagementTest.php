<?php

namespace Tests\Feature;

use App\Models\CampaignLeadStatus;
use App\Models\CampaignPaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CrmScenario;
use Tests\TestCase;

class StatusManagementTest extends TestCase
{
    use CrmScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildScenario();
    }

    public function test_a_status_can_be_created_in_many_campaigns_at_once(): void
    {
        $this->actingAs($this->super)->post('/statuses', [
            'type' => 'lead', 'name' => 'Callback Booked', 'color' => 'violet', 'campaign_ids' => [$this->iva->id, $this->solar->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        foreach ([$this->iva, $this->solar] as $campaign) {
            $status = CampaignLeadStatus::where('campaign_id', $campaign->id)->where('name', 'Callback Booked')->firstOrFail();
            $this->assertSame('violet', $status->color);
            $this->assertTrue($status->is_active);
            $this->assertSame((int) CampaignLeadStatus::where('campaign_id', $campaign->id)->max('sort_order'), $status->sort_order, 'Added at the end.');
        }
        $this->assertDatabaseHas('activity_logs', ['action' => 'campaign.statuses_changed']);
    }

    public function test_existing_statuses_are_skipped_and_default_flag_moves(): void
    {
        $this->actingAs($this->super)->post('/statuses', [
            'type' => 'payment', 'name' => 'Pending', 'color' => 'amber', 'campaign_ids' => [$this->iva->id],
        ])->assertSessionHas('error');
        $this->assertSame(1, CampaignPaymentStatus::where('campaign_id', $this->iva->id)->where('name', 'Pending')->count());

        $this->actingAs($this->super)->post('/statuses', [
            'type' => 'payment', 'name' => 'Deposit Paid', 'color' => 'sky', 'is_default' => true, 'campaign_ids' => [$this->iva->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Deposit Paid', $this->iva->defaultPaymentStatus()->name);
        $this->assertSame(1, CampaignPaymentStatus::where('campaign_id', $this->iva->id)->where('is_default', true)->count());
    }

    public function test_bulk_update_renames_across_campaigns_and_keeps_defaults_active(): void
    {
        $ids = CampaignLeadStatus::where('name', 'Contacted')->pluck('id')->all();
        $this->actingAs($this->super)->put('/statuses', ['type' => 'lead', 'status_ids' => $ids, 'name' => 'Reached', 'color' => 'sky', 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame(0, CampaignLeadStatus::where('name', 'Contacted')->count());
        $this->assertSame(2, CampaignLeadStatus::where('name', 'Reached')->where('is_active', false)->count());

        // Renaming onto an existing name in the same campaign is refused.
        $this->actingAs($this->super)->put('/statuses', ['type' => 'lead', 'status_ids' => $ids, 'name' => 'Completed', 'color' => 'sky', 'is_active' => true])->assertSessionHasErrors('name');

        // Defaults ("New") are never deactivated.
        $newIds = CampaignLeadStatus::where('name', 'New')->pluck('id')->all();
        $this->actingAs($this->super)->put('/statuses', ['type' => 'lead', 'status_ids' => $newIds, 'name' => 'New', 'color' => 'blue', 'is_active' => false]);
        $this->assertSame(2, CampaignLeadStatus::where('name', 'New')->where('is_active', true)->count());
    }

    public function test_only_super_admin_can_manage_statuses(): void
    {
        $this->actingAs($this->adminA)->post('/statuses', ['type' => 'lead', 'name' => 'X', 'color' => 'blue', 'campaign_ids' => [$this->iva->id]])->assertForbidden();
        $this->actingAs($this->managerA)->put('/statuses', ['type' => 'lead', 'status_ids' => [1], 'name' => 'X', 'color' => 'blue', 'is_active' => true])->assertForbidden();
        $this->assertDatabaseMissing('campaign_lead_statuses', ['name' => 'X']);
    }
}
