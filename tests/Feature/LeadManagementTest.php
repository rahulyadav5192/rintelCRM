<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CrmScenario;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use CrmScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildScenario();
    }

    public function test_lead_is_created_with_backend_generated_id_and_defaults(): void
    {
        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['lead_number' => 'HACKED-1', 'lead_status_id' => null]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $lead = Lead::latest('id')->first();
        $this->assertMatchesRegularExpression('/^RL-\d{6}$/', $lead->lead_number);
        $this->assertSame($this->branchA->id, $lead->branch_id);
        $this->assertSame($this->managerA->id, $lead->assigned_to);
        $this->assertSame($this->iva->defaultLeadStatus()->id, $lead->lead_status_id);
        $this->assertSame($this->iva->defaultPaymentStatus()->id, $lead->payment_status_id);
        $this->assertSame($this->branchAdminA->id, $lead->created_by);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'created']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'assigned']);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $lead->id, 'action' => 'lead.created']);
    }

    public function test_branch_admin_cannot_create_leads_in_another_branch(): void
    {
        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['branch_id' => $this->branchB->id, 'assigned_to' => $this->managerB->id]))
            ->assertSessionHasErrors('branch_id');
        $this->assertSame(0, Lead::where('first_name', 'John')->count());
    }

    public function test_assignment_is_restricted_to_eligible_managers(): void
    {
        // Manager from another branch.
        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['assigned_to' => $this->managerB->id]))->assertSessionHasErrors('assigned_to');
        // Manager of another campaign in the same branch.
        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['assigned_to' => $this->solarManagerA->id]))->assertSessionHasErrors('assigned_to');
        // A branch admin is never an eligible advisor.
        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['assigned_to' => $this->branchAdminA->id]))->assertSessionHasErrors('assigned_to');
        // Inactive manager.
        $inactive = User::factory()->manager($this->branchA)->withCampaigns([$this->iva])->inactive()->create();
        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['assigned_to' => $inactive->id]))->assertSessionHasErrors('assigned_to');
    }

    public function test_manager_cannot_assign_a_lead_to_an_unauthorized_manager(): void
    {
        $this->managerA->syncPermissions([Permission::AssignLeads->value]);
        $payload = $this->leadPayload([...$this->ivaLeadA->only('title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code'), 'lead_status_id' => $this->ivaLeadA->lead_status_id]);

        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", [...$payload, 'assigned_to' => $this->managerB->id])->assertSessionHasErrors('assigned_to');
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", [...$payload, 'assigned_to' => $this->solarManagerA->id])->assertSessionHasErrors('assigned_to');
        $this->assertSame($this->managerA->id, $this->ivaLeadA->fresh()->assigned_to);

        // Without the assign permission, any change of advisor is refused outright.
        $this->managerA->syncPermissions([Permission::UpdateLeadStatus->value]);
        $colleague = User::factory()->manager($this->branchA)->withCampaigns([$this->iva])->create();
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", [...$payload, 'assigned_to' => $colleague->id])->assertForbidden();
    }

    public function test_reassignment_to_eligible_manager_is_logged(): void
    {
        $colleague = User::factory()->manager($this->branchA)->withCampaigns([$this->iva])->create();
        $payload = $this->leadPayload([...$this->ivaLeadA->only('title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code'), 'lead_status_id' => $this->ivaLeadA->lead_status_id, 'payment_status_id' => $this->ivaLeadA->payment_status_id, 'assigned_to' => $colleague->id]);
        $this->actingAs($this->branchAdminA)->put("/leads/{$this->ivaLeadA->id}", $payload)->assertSessionHasNoErrors();
        $this->assertSame($colleague->id, $this->ivaLeadA->fresh()->assigned_to);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'reassigned']);
    }

    public function test_campaign_specific_lead_statuses_are_validated(): void
    {
        $solarStatus = $this->solar->leadStatuses()->first();
        $this->actingAs($this->super)->post('/leads', $this->leadPayload(['lead_status_id' => $solarStatus->id]))->assertSessionHasErrors('lead_status_id');

        $inactive = $this->iva->leadStatuses()->where('name', 'Rejected')->first();
        $inactive->update(['is_active' => false]);
        $this->actingAs($this->super)->post('/leads', $this->leadPayload(['lead_status_id' => $inactive->id]))->assertSessionHasErrors('lead_status_id');

        $valid = $this->iva->leadStatuses()->where('name', 'Contacted')->first();
        $this->actingAs($this->super)->post('/leads', $this->leadPayload(['lead_status_id' => $valid->id]))->assertSessionHasNoErrors();
        $this->assertSame($valid->id, Lead::latest('id')->first()->lead_status_id);
    }

    public function test_campaign_specific_payment_statuses_are_validated(): void
    {
        $solarPayment = $this->solar->paymentStatuses()->first();
        $this->actingAs($this->super)->post('/leads', $this->leadPayload(['payment_status_id' => $solarPayment->id]))->assertSessionHasErrors('payment_status_id');

        $payload = $this->leadPayload([...$this->ivaLeadA->only('title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code'), 'lead_status_id' => $this->ivaLeadA->lead_status_id, 'payment_status_id' => $solarPayment->id]);
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", $payload)->assertSessionHasErrors('payment_status_id');

        $paid = $this->iva->paymentStatuses()->where('name', 'Paid')->first();
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", [...$payload, 'payment_status_id' => $paid->id])->assertSessionHasNoErrors();
        $this->assertSame($paid->id, $this->ivaLeadA->fresh()->payment_status_id);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'payment_status_changed']);
    }

    public function test_database_rejects_a_status_from_another_campaign(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->ivaLeadA->lead_status_id = $this->solar->leadStatuses()->first()->id;
        $this->ivaLeadA->save();
    }

    public function test_changing_campaign_resets_statuses_to_the_new_campaigns_defaults(): void
    {
        $this->ivaLeadA->assigned_to = null;
        $this->ivaLeadA->save();
        $payload = $this->leadPayload([...$this->ivaLeadA->only('title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code'), 'campaign_id' => $this->solar->id, 'assigned_to' => null, 'lead_status_id' => $this->ivaLeadA->lead_status_id]);
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", $payload)->assertSessionHasNoErrors();

        $lead = $this->ivaLeadA->fresh();
        $this->assertSame($this->solar->id, $lead->campaign_id);
        $this->assertSame($this->solar->defaultLeadStatus()->id, $lead->lead_status_id);
    }

    public function test_lookup_returns_only_eligible_managers_and_campaign_statuses(): void
    {
        $this->actingAs($this->super)->getJson("/lookup/branches/{$this->branchA->id}/campaigns/{$this->iva->id}")
            ->assertOk()
            ->assertJsonPath('managers.0.id', $this->managerA->id)
            ->assertJsonCount(1, 'managers')
            ->assertJsonCount(4, 'lead_statuses')
            ->assertJsonCount(3, 'payment_statuses');
    }

    public function test_users_without_export_permission_cannot_export(): void
    {
        $this->actingAs($this->managerA)->get('/leads/export')->assertForbidden();
        $this->actingAs($this->branchAdminA)->get('/leads/export?format=xlsx')->assertForbidden();
        $this->actingAs($this->managerA)->get('/reports?export=csv')->assertForbidden();
    }

    public function test_export_applies_the_same_scope_as_the_listing(): void
    {
        $this->branchAdminA->syncPermissions([Permission::ExportLeads->value]);
        $response = $this->actingAs($this->branchAdminA)->get("/leads/export?format=csv&branch_id={$this->branchB->id}")->assertOk();
        $csv = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringNotContainsString($this->ivaLeadB->lead_number, $csv);

        $response = $this->actingAs($this->branchAdminA)->get('/leads/export?format=csv')->assertOk();
        $csv = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString($this->ivaLeadA->lead_number, $csv);
        $this->assertStringNotContainsString($this->ivaLeadB->lead_number, $csv);
        $this->assertStringNotContainsString($this->solarLeadA->lead_number, $csv);
        $this->assertDatabaseHas('activity_logs', ['action' => 'leads.exported', 'user_id' => $this->branchAdminA->id]);

        $this->actingAs($this->super)->get('/leads/export?format=xlsx')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_listing_filters_work_on_the_backend(): void
    {
        $response = $this->actingAs($this->super)->get("/leads?campaign_id={$this->solar->id}");
        $this->assertEquals([$this->solarLeadA->id], $this->listedLeadIds($response));

        $response = $this->actingAs($this->super)->get('/leads?search='.urlencode($this->ivaLeadB->lead_number));
        $this->assertEquals([$this->ivaLeadB->id], $this->listedLeadIds($response));

        $response = $this->actingAs($this->super)->get("/leads?assigned_to={$this->managerB->id}");
        $this->assertEquals([$this->ivaLeadB->id], $this->listedLeadIds($response));
    }

    public function test_notes_are_recorded_in_the_timeline(): void
    {
        $this->actingAs($this->managerA)->post("/leads/{$this->ivaLeadA->id}/notes", ['body' => 'Called, no answer'])->assertRedirect();
        $this->assertDatabaseHas('lead_notes', ['lead_id' => $this->ivaLeadA->id, 'body' => 'Called, no answer', 'user_id' => $this->managerA->id]);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'note_added']);
    }

    public function test_all_pages_render_for_each_role(): void
    {
        $pages = ['/dashboard', '/leads', "/leads/{$this->ivaLeadA->id}", '/profile'];
        foreach ([$this->super, $this->branchAdminA, $this->managerA] as $user) {
            foreach ($pages as $page) {
                $this->actingAs($user)->get($page)->assertOk();
            }
        }
        foreach (['/leads/create', "/leads/{$this->ivaLeadA->id}/edit", '/branches', "/branches/{$this->branchA->id}", '/branches/create', "/branches/{$this->branchA->id}/edit",
            '/campaigns', "/campaigns/{$this->iva->id}", '/campaigns/create', "/campaigns/{$this->iva->id}/edit", "/campaigns/{$this->iva->id}/statuses",
            '/users', "/users/{$this->managerA->id}", '/users/create', "/users/{$this->managerA->id}/edit", '/public-forms', '/reports', '/reports?type=date', '/activity-logs', '/settings'] as $page) {
            $this->actingAs($this->super)->get($page)->assertOk();
        }
        $this->actingAs($this->super)->getJson('/notifications')->assertOk();
    }
}
