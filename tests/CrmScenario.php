<?php

namespace Tests;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;

/**
 * Builds a small two-branch world used across authorization tests:
 *
 *  Branch A: campaigns IVA + SOLAR       Branch B: campaign IVA
 *  - branchAdminA (IVA only)             - branchAdminB (IVA)
 *  - managerA (IVA)                      - managerB (IVA)
 *  - solarManagerA (SOLAR)
 */
trait CrmScenario
{
    protected User $super;

    protected User $branchAdminA;

    protected User $branchAdminB;

    protected User $managerA;

    protected User $solarManagerA;

    protected User $managerB;

    protected Branch $branchA;

    protected Branch $branchB;

    protected Campaign $iva;

    protected Campaign $solar;

    protected Lead $ivaLeadA;

    protected Lead $solarLeadA;

    protected Lead $ivaLeadB;

    protected function buildScenario(): void
    {
        $this->branchA = Branch::factory()->create(['name' => 'Branch A']);
        $this->branchB = Branch::factory()->create(['name' => 'Branch B']);
        $this->iva = Campaign::factory()->forBranches([$this->branchA, $this->branchB])->create(['name' => 'IVA']);
        $this->solar = Campaign::factory()->forBranches([$this->branchA])->create(['name' => 'Solar']);

        $this->super = User::factory()->superAdmin()->create();
        $this->branchAdminA = User::factory()->branchAdmin($this->branchA)->withCampaigns([$this->iva])
            ->withPermissions([Permission::CreateLeads, Permission::EditLeads, Permission::AssignLeads, Permission::UpdateLeadStatus, Permission::UpdatePaymentStatus])->create();
        $this->branchAdminB = User::factory()->branchAdmin($this->branchB)->withCampaigns([$this->iva])->withDefaultPermissions()->create();
        $this->managerA = User::factory()->manager($this->branchA)->withCampaigns([$this->iva])->withPermissions([Permission::UpdateLeadStatus])->create();
        $this->solarManagerA = User::factory()->manager($this->branchA)->withCampaigns([$this->solar])->withDefaultPermissions()->create();
        $this->managerB = User::factory()->manager($this->branchB)->withCampaigns([$this->iva])->withDefaultPermissions()->create();

        $this->ivaLeadA = Lead::factory()->for_($this->branchA, $this->iva, $this->managerA)->create();
        $this->solarLeadA = Lead::factory()->for_($this->branchA, $this->solar, $this->solarManagerA)->create();
        $this->ivaLeadB = Lead::factory()->for_($this->branchB, $this->iva, $this->managerB)->create();
    }

    protected function leadPayload(array $overrides = []): array
    {
        return [
            'title' => 'Mr',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'mobile' => '07700900123',
            'email' => 'john@example.com',
            'house_number' => '12',
            'post_code' => 'SW1A 1AA',
            'branch_id' => $this->branchA->id,
            'campaign_id' => $this->iva->id,
            'assigned_to' => $this->managerA->id,
            ...$overrides,
        ];
    }

    protected function listedLeadIds($response): array
    {
        return collect($response->viewData('page')['props']['leads']['data'])->pluck('id')->sort()->values()->all();
    }
}
