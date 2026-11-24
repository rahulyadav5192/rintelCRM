<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\QueryException;
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

    public function test_lead_is_created_with_backend_generated_id_owner_and_defaults(): void
    {
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['lead_number' => 'HACKED-1', 'lead_status_id' => null]))
            ->assertSessionHasNoErrors()->assertRedirect();

        $lead = Lead::latest('id')->first();
        $this->assertMatchesRegularExpression('/^RL-\d{6}$/', $lead->lead_number);
        $this->assertSame($this->adminA->id, $lead->admin_id, 'Leads an admin creates are owned by that admin.');
        $this->assertSame($this->managerA->id, $lead->assigned_to);
        $this->assertSame($this->iva->defaultLeadStatus()->id, $lead->lead_status_id);
        $this->assertSame($this->iva->defaultPaymentStatus()->id, $lead->payment_status_id);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'created']);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'type' => 'assigned']);
        $this->assertDatabaseHas('activity_logs', ['subject_id' => $lead->id, 'action' => 'lead.created']);
    }

    public function test_admin_cannot_create_leads_owned_by_another_admin(): void
    {
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['admin_id' => $this->adminB->id]))->assertSessionHasErrors('admin_id');
        $this->assertSame(0, Lead::where('first_name', 'John')->count());
    }

    public function test_super_admin_can_only_pick_an_owner_who_has_the_campaign(): void
    {
        $this->actingAs($this->super)->post('/leads', $this->leadPayload(['campaign_id' => $this->solar->id, 'assigned_to' => null, 'admin_id' => $this->adminA->id]))
            ->assertSessionHasErrors('admin_id');
        $this->actingAs($this->super)->post('/leads', $this->leadPayload(['admin_id' => $this->adminB->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->adminB->id, Lead::latest('id')->first()->admin_id);

        // Only Super Admins may move a lead to another admin.
        $this->actingAs($this->adminA)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['admin_id' => $this->adminB->id]))->assertForbidden();
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['admin_id' => $this->adminB->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->adminB->id, $this->ivaLeadA->fresh()->admin_id);
    }

    public function test_assignment_is_restricted_to_active_managers_of_the_campaign(): void
    {
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['assigned_to' => $this->solarManager->id]))->assertSessionHasErrors('assigned_to');
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['assigned_to' => $this->adminA->id]))->assertSessionHasErrors('assigned_to');
        $inactive = User::factory()->manager()->withCampaigns([$this->iva])->inactive()->create();
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['assigned_to' => $inactive->id]))->assertSessionHasErrors('assigned_to');
        // Managers are global: any active IVA manager is eligible.
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['assigned_to' => $this->managerB->id]))->assertSessionHasNoErrors();
    }

    public function test_manager_cannot_assign_a_lead_to_an_unauthorized_manager(): void
    {
        $this->managerA->syncPermissions([Permission::AssignLeads->value]);
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['assigned_to' => $this->solarManager->id]))->assertSessionHasErrors('assigned_to');
        $this->assertSame($this->managerA->id, $this->ivaLeadA->fresh()->assigned_to);

        $this->managerA->syncPermissions([Permission::UpdateLeadStatus->value]);
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['assigned_to' => $this->managerB->id]))->assertForbidden();
    }

    public function test_reassignment_to_eligible_manager_is_logged(): void
    {
        $this->actingAs($this->adminA)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['assigned_to' => $this->managerB->id]))->assertSessionHasNoErrors();
        $this->assertSame($this->managerB->id, $this->ivaLeadA->fresh()->assigned_to);
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
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['payment_status_id' => $solarPayment->id]))->assertSessionHasErrors('payment_status_id');

        $paid = $this->iva->paymentStatuses()->where('name', 'Paid')->first();
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['payment_status_id' => $paid->id]))->assertSessionHasNoErrors();
        $this->assertSame($paid->id, $this->ivaLeadA->fresh()->payment_status_id);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'payment_status_changed']);
    }

    public function test_database_rejects_a_status_from_another_campaign(): void
    {
        $this->expectException(QueryException::class);
        $this->ivaLeadA->lead_status_id = $this->solar->leadStatuses()->first()->id;
        $this->ivaLeadA->save();
    }

    public function test_changing_campaign_resets_statuses_and_rechecks_owner(): void
    {
        // adminA does not have Solar, so moving their lead there is refused.
        $payload = $this->currentPayload($this->ivaLeadA, ['campaign_id' => $this->solar->id, 'assigned_to' => null]);
        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", $payload)->assertSessionHasErrors('admin_id');

        $this->actingAs($this->super)->put("/leads/{$this->ivaLeadA->id}", [...$payload, 'admin_id' => null])->assertSessionHasNoErrors();
        $lead = $this->ivaLeadA->fresh();
        $this->assertSame($this->solar->id, $lead->campaign_id);
        $this->assertNull($lead->admin_id);
        $this->assertSame($this->solar->defaultLeadStatus()->id, $lead->lead_status_id);
    }

    public function test_lookup_returns_only_campaign_managers_admins_and_statuses(): void
    {
        $this->actingAs($this->super)->getJson("/lookup/campaigns/{$this->iva->id}")
            ->assertOk()
            ->assertJsonCount(2, 'managers')
            ->assertJsonCount(2, 'admins')
            ->assertJsonCount(4, 'lead_statuses')
            ->assertJsonCount(3, 'payment_statuses');
        // Admins never choose an owner, so they are not shown the list of admins.
        $this->actingAs($this->adminA)->getJson("/lookup/campaigns/{$this->iva->id}")->assertOk()->assertJsonCount(0, 'admins');
    }

    public function test_users_without_export_permission_cannot_export(): void
    {
        $this->actingAs($this->managerA)->get('/leads/export')->assertForbidden();
        $this->actingAs($this->adminA)->get('/leads/export?format=xlsx')->assertForbidden();
        $this->actingAs($this->managerA)->get('/reports?export=csv')->assertForbidden();
    }

    public function test_export_applies_the_same_scope_as_the_listing(): void
    {
        $this->adminA->syncPermissions([Permission::ExportLeads->value]);
        $response = $this->actingAs($this->adminA)->get('/leads/export?format=csv')->assertOk();
        $csv = file_get_contents($response->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString($this->ivaLeadA->lead_number, $csv);
        $this->assertStringNotContainsString($this->ivaLeadB->lead_number, $csv);
        $this->assertStringNotContainsString($this->solarLead->lead_number, $csv);
        $this->assertDatabaseHas('activity_logs', ['action' => 'leads.exported', 'user_id' => $this->adminA->id]);

        $this->actingAs($this->super)->get('/leads/export?format=xlsx')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_listing_filters_work_on_the_backend(): void
    {
        $this->assertEquals([$this->solarLead->id], $this->listedLeadIds($this->actingAs($this->super)->get("/leads?campaign_id={$this->solar->id}")));
        $this->assertEquals([$this->ivaLeadB->id], $this->listedLeadIds($this->actingAs($this->super)->get('/leads?search='.urlencode($this->ivaLeadB->lead_number))));
        $this->assertEquals([$this->ivaLeadB->id], $this->listedLeadIds($this->actingAs($this->super)->get("/leads?admin_id={$this->adminB->id}")));
        $this->assertEquals([$this->solarLead->id], $this->listedLeadIds($this->actingAs($this->super)->get('/leads?admin_id=none')));
    }

    public function test_notes_are_recorded_in_the_timeline(): void
    {
        $this->actingAs($this->managerA)->post("/leads/{$this->ivaLeadA->id}/notes", ['body' => 'Called, no answer'])->assertRedirect();
        $this->assertDatabaseHas('lead_notes', ['lead_id' => $this->ivaLeadA->id, 'body' => 'Called, no answer', 'user_id' => $this->managerA->id]);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'note_added']);
    }

    public function test_all_pages_render_for_each_role(): void
    {
        foreach ([$this->super, $this->adminA, $this->managerA] as $user) {
            foreach (['/dashboard', '/leads', "/leads/{$this->ivaLeadA->id}", '/profile'] as $page) {
                $this->actingAs($user)->get($page)->assertOk();
            }
        }
        $this->actingAs($this->adminA)->get('/public-forms')->assertOk();
        $this->actingAs($this->managerA)->get('/public-forms')->assertForbidden();
        foreach (['/leads/create', "/leads/{$this->ivaLeadA->id}/edit", '/campaigns', "/campaigns/{$this->iva->id}", '/campaigns/create', "/campaigns/{$this->iva->id}/edit", "/campaigns/{$this->iva->id}/statuses",
            '/statuses', '/statuses?type=payment', '/users', "/users/{$this->managerA->id}", "/users/{$this->adminA->id}", '/users/create', "/users/{$this->managerA->id}/edit", '/public-forms', '/reports', '/reports?type=date', '/reports?type=admin', '/activity-logs', '/settings'] as $page) {
            $this->actingAs($this->super)->get($page)->assertOk();
        }
        $this->actingAs($this->super)->getJson('/notifications')->assertOk();
    }
}
