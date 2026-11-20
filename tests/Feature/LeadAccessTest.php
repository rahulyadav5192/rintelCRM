<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Lead;
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
        $this->assertEquals(
            collect([$this->ivaLeadA, $this->solarLeadA, $this->ivaLeadB])->pluck('id')->sort()->values()->all(),
            $this->listedLeadIds($response),
        );

        foreach ([$this->ivaLeadA, $this->solarLeadA, $this->ivaLeadB] as $lead) {
            $this->actingAs($this->super)->get("/leads/{$lead->id}")->assertOk();
        }
    }

    public function test_branch_admin_cannot_access_another_branchs_leads(): void
    {
        $response = $this->actingAs($this->branchAdminA)->get('/leads')->assertOk();
        $this->assertEquals([$this->ivaLeadA->id], $this->listedLeadIds($response));

        $this->actingAs($this->branchAdminA)->get("/leads/{$this->ivaLeadB->id}")->assertForbidden();
        $this->actingAs($this->branchAdminA)->get("/leads/{$this->ivaLeadB->id}/edit")->assertForbidden();
        $this->actingAs($this->branchAdminA)->put("/leads/{$this->ivaLeadB->id}", $this->leadPayload(['branch_id' => $this->branchB->id]))->assertForbidden();

        // A branch filter for another branch yields nothing rather than leaking data.
        $response = $this->actingAs($this->branchAdminA)->get("/leads?branch_id={$this->branchB->id}")->assertOk();
        $this->assertEquals([], $this->listedLeadIds($response));
    }

    public function test_branch_admin_cannot_access_unassigned_campaigns(): void
    {
        // Solar is in Branch A, but not assigned to branchAdminA.
        $this->actingAs($this->branchAdminA)->get("/leads/{$this->solarLeadA->id}")->assertForbidden();
        $response = $this->actingAs($this->branchAdminA)->get("/leads?campaign_id={$this->solar->id}")->assertOk();
        $this->assertEquals([], $this->listedLeadIds($response));

        $this->actingAs($this->branchAdminA)->post('/leads', $this->leadPayload(['campaign_id' => $this->solar->id, 'assigned_to' => null]))
            ->assertSessionHasErrors('campaign_id');
        $this->actingAs($this->branchAdminA)->getJson("/lookup/branches/{$this->branchA->id}/campaigns/{$this->solar->id}")->assertForbidden();
        $this->actingAs($this->branchAdminA)->get("/campaigns/{$this->solar->id}")->assertForbidden();
    }

    public function test_manager_cannot_access_unassigned_campaigns(): void
    {
        $response = $this->actingAs($this->managerA)->get('/leads')->assertOk();
        $this->assertEquals([$this->ivaLeadA->id], $this->listedLeadIds($response));

        $this->actingAs($this->managerA)->get("/leads/{$this->solarLeadA->id}")->assertForbidden();
        $this->actingAs($this->managerA)->post("/leads/{$this->solarLeadA->id}/notes", ['body' => 'hi'])->assertForbidden();
        $this->actingAs($this->managerA)->getJson("/lookup/branches/{$this->branchA->id}/campaigns/{$this->solar->id}")->assertForbidden();
    }

    public function test_manager_with_assigned_visibility_only_sees_own_leads(): void
    {
        $other = User::factory()->manager($this->branchA)->withCampaigns([$this->iva])->create();
        $othersLead = Lead::factory()->for_($this->branchA, $this->iva, $other)->create();
        $this->managerA->lead_visibility = User::VISIBILITY_ASSIGNED;
        $this->managerA->save();

        $response = $this->actingAs($this->managerA)->get('/leads')->assertOk();
        $this->assertEquals([$this->ivaLeadA->id], $this->listedLeadIds($response));
        $this->actingAs($this->managerA)->get("/leads/{$othersLead->id}")->assertForbidden();
    }

    public function test_manager_cannot_edit_leads_without_edit_permission(): void
    {
        $payload = $this->leadPayload([...$this->ivaLeadA->only('title', 'mobile', 'email', 'house_number', 'post_code', 'last_name'), 'first_name' => 'Changed', 'lead_status_id' => $this->ivaLeadA->lead_status_id]);
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", $payload)->assertForbidden();
        $this->assertNotEquals('Changed', $this->ivaLeadA->fresh()->first_name);

        // But the status permission they do hold works.
        $newStatus = $this->iva->leadStatuses()->where('name', 'Contacted')->first();
        $payload = $this->leadPayload([...$this->ivaLeadA->only('title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code'), 'lead_status_id' => $newStatus->id]);
        $this->actingAs($this->managerA)->put("/leads/{$this->ivaLeadA->id}", $payload)->assertRedirect();
        $this->assertEquals($newStatus->id, $this->ivaLeadA->fresh()->lead_status_id);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->ivaLeadA->id, 'type' => 'status_changed']);
    }

    public function test_unauthorized_users_cannot_access_leads_by_changing_the_id_in_the_url(): void
    {
        foreach ([$this->branchAdminB, $this->managerB] as $user) {
            $this->actingAs($user)->get("/leads/{$this->ivaLeadA->id}")->assertForbidden();
            $this->actingAs($user)->get("/leads/{$this->ivaLeadA->id}/edit")->assertForbidden();
            $this->actingAs($user)->post("/leads/{$this->ivaLeadA->id}/notes", ['body' => 'x'])->assertForbidden();
            $this->actingAs($user)->delete("/leads/{$this->ivaLeadA->id}")->assertForbidden();
        }
        $this->assertNotSoftDeleted($this->ivaLeadA);
    }

    public function test_deleting_requires_permission(): void
    {
        $this->actingAs($this->branchAdminA)->delete("/leads/{$this->ivaLeadA->id}")->assertForbidden();
        $this->actingAs($this->super)->delete("/leads/{$this->ivaLeadA->id}")->assertRedirect('/leads');
        $this->assertSoftDeleted($this->ivaLeadA);
    }

    public function test_dashboard_statistics_are_scoped(): void
    {
        $this->actingAs($this->branchAdminA)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('cards.total', 1));
        $this->actingAs($this->super)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('cards.total', 3));
        // A branch filter on the dashboard cannot widen a branch admin's scope.
        $this->actingAs($this->branchAdminA)->get("/dashboard?branch_id={$this->branchB->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('cards.total', 1));
    }

    public function test_deactivated_users_cannot_log_in(): void
    {
        $user = User::factory()->manager($this->branchA)->inactive()->create(['email' => 'inactive@example.com']);
        $this->post('/login', ['email' => 'inactive@example.com', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // An active session is terminated once the account is deactivated.
        $this->managerA->is_active = false;
        $this->managerA->save();
        $this->actingAs($this->managerA)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_only_super_admin_can_manage_branches_campaigns_and_logs(): void
    {
        foreach ([$this->branchAdminA, $this->managerA] as $user) {
            $this->actingAs($user)->get('/branches')->assertForbidden();
            $this->actingAs($user)->get('/campaigns/create')->assertForbidden();
            $this->actingAs($user)->get('/activity-logs')->assertForbidden();
            $this->actingAs($user)->get('/public-forms')->assertForbidden();
            $this->actingAs($user)->get('/settings')->assertForbidden();
        }
        $this->actingAs($this->super)->get('/branches')->assertOk();
        $this->actingAs($this->super)->get('/activity-logs')->assertOk();
    }

    public function test_branch_admin_with_manage_users_only_sees_and_creates_managers_in_own_branch(): void
    {
        $this->branchAdminA->syncPermissions([Permission::ManageUsers->value, Permission::UpdateLeadStatus->value]);

        $response = $this->actingAs($this->branchAdminA)->get('/users')->assertOk();
        $ids = collect($response->viewData('page')['props']['users']['data'])->pluck('id')->sort()->values()->all();
        $this->assertEquals(collect([$this->managerA, $this->solarManagerA])->pluck('id')->sort()->values()->all(), $ids);
        $this->actingAs($this->branchAdminA)->get("/users/{$this->managerB->id}")->assertForbidden();

        $base = ['name' => 'New M', 'email' => 'm@example.com', 'password' => 'secret123', 'role' => 'manager', 'branch_id' => $this->branchA->id, 'campaign_ids' => [$this->iva->id]];
        $this->actingAs($this->branchAdminA)->post('/users', [...$base, 'role' => 'super_admin'])->assertSessionHasErrors('role');
        $this->actingAs($this->branchAdminA)->post('/users', [...$base, 'branch_id' => $this->branchB->id])->assertSessionHasErrors('branch_id');
        $this->actingAs($this->branchAdminA)->post('/users', [...$base, 'campaign_ids' => [$this->solar->id]])->assertSessionHasErrors('campaign_ids');
        $this->actingAs($this->branchAdminA)->post('/users', [...$base, 'permissions' => ['export_leads']])->assertSessionHasErrors('permissions');
        $this->actingAs($this->branchAdminA)->post('/users', [...$base, 'permissions' => ['update_lead_status']])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'm@example.com', 'branch_id' => $this->branchA->id, 'role' => 'manager']);
    }
}
