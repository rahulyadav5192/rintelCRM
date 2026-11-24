<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CrmScenario;
use Tests\TestCase;

class LeadAccessTest extends TestCase
{
    use CrmScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildScenario();
    }

    public function test_super_admin_can_access_all_leads(): void
    {
        $response = $this->actingAs($this->super)->get('/leads')->assertOk();
        $this->assertEquals($this->ids($this->ivaLeadA, $this->ivaLeadB, $this->solarLead), $this->listedLeadIds($response));

        foreach ([$this->ivaLeadA, $this->ivaLeadB, $this->solarLead] as $lead) {
            $this->actingAs($this->super)->get("/leads/{$lead->id}")->assertOk();
        }
    }

    public function test_admin_only_sees_their_own_leads_even_in_a_shared_campaign(): void
    {
        $response = $this->actingAs($this->adminA)->get('/leads')->assertOk();
        $this->assertEquals([$this->ivaLeadA->id], $this->listedLeadIds($response));

        $this->actingAs($this->adminA)->get("/leads/{$this->ivaLeadB->id}")->assertForbidden();
        $this->actingAs($this->adminA)->get("/leads/{$this->ivaLeadB->id}/edit")->assertForbidden();
        $this->actingAs($this->adminA)->put("/leads/{$this->ivaLeadB->id}", $this->currentPayload($this->ivaLeadB))->assertForbidden();

        // Filtering by another admin yields nothing rather than leaking data.
        $response = $this->actingAs($this->adminA)->get("/leads?admin_id={$this->adminB->id}")->assertOk();
        $this->assertEquals([], $this->listedLeadIds($response));
    }

    public function test_admin_loses_access_to_own_lead_when_campaign_is_unassigned(): void
    {
        $this->adminA->campaigns()->detach($this->iva->id);
        $this->actingAs($this->adminA->fresh())->get("/leads/{$this->ivaLeadA->id}")->assertForbidden();
    }

    public function test_admin_cannot_access_unassigned_campaigns(): void
    {
        $this->actingAs($this->adminA)->get("/leads/{$this->solarLead->id}")->assertForbidden();
        $this->actingAs($this->adminA)->post('/leads', $this->leadPayload(['campaign_id' => $this->solar->id, 'assigned_to' => null]))
            ->assertSessionHasErrors('campaign_id');
        $this->actingAs($this->adminA)->getJson("/lookup/campaigns/{$this->solar->id}")->assertForbidden();
        $this->actingAs($this->adminA)->get("/campaigns/{$this->solar->id}")->assertForbidden();
    }

    public function test_manager_sees_all_leads_in_their_campaigns_but_no_others(): void
    {
        $response = $this->actingAs($this->managerA)->get('/leads')->assertOk();
        $this->assertEquals($this->ids($this->ivaLeadA, $this->ivaLeadB), $this->listedLeadIds($response));

        $this->actingAs($this->managerA)->get("/leads/{$this->solarLead->id}")->assertForbidden();
        $this->actingAs($this->managerA)->post("/leads/{$this->solarLead->id}/notes", ['body' => 'hi'])->assertForbidden();
        $this->actingAs($this->managerA)->getJson("/lookup/campaigns/{$this->solar->id}")->assertForbidden();
    }

    public function test_manager_with_assigned_visibility_only_sees_own_leads(): void
    {
        $this->managerA->lead_visibility = User::VISIBILITY_ASSIGNED;
        $this->managerA->save();

        $response = $this->actingAs($this->managerA)->get('/leads')->assertOk();
        $this->assertEquals([$this->ivaLeadA->id], $this->listedLeadIds($response));
        $this->actingAs($this->managerA)->get("/leads/{$this->ivaLeadB->id}")->assertForbidden();
    }

    public function test_manager_cannot_edit_leads_without_edit_permission(): void
    {
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['first_name' => 'Changed']))->assertForbidden();
        $this->assertNotEquals('Changed', $this->ivaLeadA->fresh()->first_name);

        // The status permission they do hold works.
        $newStatus = $this->iva->leadStatuses()->where('name', 'Contacted')->first();
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", $this->currentPayload($this->ivaLeadA, ['lead_status_id' => $newStatus->id]))->assertRedirect();
        $this->assertEquals($newStatus->id, $this->ivaLeadA->fresh()->lead_status_id);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'status_changed']);
    }

    public function test_unauthorized_users_cannot_access_leads_by_changing_the_id_in_the_url(): void
    {
        foreach ([$this->adminB, $this->solarManager] as $user) {
            $this->actingAs($user)->get("/leads/{$this->ivaLeadA->id}")->assertForbidden();
            $this->actingAs($user)->get("/leads/{$this->ivaLeadA->id}/edit")->assertForbidden();
            $this->actingAs($user)->post("/leads/{$this->ivaLeadA->id}/notes", ['body' => 'x'])->assertForbidden();
            $this->actingAs($user)->delete("/leads/{$this->ivaLeadA->id}")->assertForbidden();
        }
        $this->assertNotSoftDeleted($this->ivaLeadA);
    }

    public function test_deleting_requires_permission(): void
    {
        $this->actingAs($this->adminA)->delete("/leads/{$this->ivaLeadA->id}")->assertForbidden();
        $this->actingAs($this->super)->delete("/leads/{$this->ivaLeadA->id}")->assertRedirect('/leads');
        $this->assertSoftDeleted($this->ivaLeadA);
    }

    public function test_dashboard_statistics_are_scoped(): void
    {
        $this->actingAs($this->adminA)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('cards.total', 1));
        $this->actingAs($this->managerA)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('cards.total', 2));
        $this->actingAs($this->super)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('cards.total', 3));
        // An admin filter on the dashboard cannot widen an admin's scope.
        $this->actingAs($this->adminA)->get("/dashboard?admin_id={$this->adminB->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('cards.total', 1));
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        User::factory()->manager()->inactive()->create(['email' => 'inactive@example.com']);
        $this->post('/login', ['email' => 'inactive@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->managerA->is_active = false;
        $this->managerA->save();
        $this->actingAs($this->managerA)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_only_super_admin_can_manage_users_campaigns_statuses_and_logs(): void
    {
        foreach ([$this->adminA, $this->managerA] as $user) {
            $this->actingAs($user)->get('/users')->assertForbidden();
            $this->actingAs($user)->get('/users/create')->assertForbidden();
            $this->actingAs($user)->post('/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret123', 'role' => 'manager', 'campaign_ids' => [$this->iva->id]])->assertForbidden();
            $this->actingAs($user)->get("/users/{$this->managerB->id}/edit")->assertForbidden();
            $this->actingAs($user)->get('/campaigns/create')->assertForbidden();
            $this->actingAs($user)->get('/statuses')->assertForbidden();
            $this->actingAs($user)->get('/activity-logs')->assertForbidden();
            $this->actingAs($user)->get('/settings')->assertForbidden();
        }
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
        $this->actingAs($this->super)->get('/users')->assertOk();
        $this->actingAs($this->super)->get('/statuses')->assertOk();
    }

    public function test_super_admin_creates_admins_and_managers_without_a_branch(): void
    {
        $this->actingAs($this->super)->post('/users', [
            'name' => 'New Admin', 'email' => 'newadmin@example.com', 'password' => 'secret123', 'role' => 'admin',
            'campaign_ids' => [$this->iva->id], 'permissions' => [Permission::CreateLeads->value],
        ])->assertSessionHasNoErrors();
        $admin = User::where('email', 'newadmin@example.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertNotNull($admin->publicForm, 'Every admin gets a public form URL automatically.');

        $this->actingAs($this->super)->post('/users', [
            'name' => 'New Manager', 'email' => 'newmanager@example.com', 'password' => 'secret123', 'role' => 'manager',
            'campaign_ids' => [], 'permissions' => [],
        ])->assertSessionHasErrors('campaign_ids');
    }
}
