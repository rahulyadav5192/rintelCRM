<?php

namespace Tests;

use App\Enums\Permission;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;

/**
 * Shared world for authorization tests:
 *
 *  Campaigns: IVA, Solar
 *  - adminA (IVA)  owns ivaLeadA          - adminB (IVA)  owns ivaLeadB
 *  - managerA (IVA, status permission)    - managerB (IVA)
 *  - solarManager (Solar)                 - solarLead: Solar, no owning admin
 */
trait CrmScenario
{
    protected User $super;

    protected User $adminA;

    protected User $adminB;

    protected User $managerA;

    protected User $managerB;

    protected User $solarManager;

    protected Campaign $iva;

    protected Campaign $solar;

    protected Lead $ivaLeadA;

    protected Lead $ivaLeadB;

    protected Lead $solarLead;

    protected function buildScenario(): void
    {
        $this->iva = Campaign::factory()->create(['name' => 'IVA']);
        $this->solar = Campaign::factory()->create(['name' => 'Solar']);

        $this->super = User::factory()->superAdmin()->create();
        $this->adminA = User::factory()->admin()->withCampaigns([$this->iva])
            ->withPermissions([Permission::CreateLeads, Permission::EditLeads, Permission::AssignLeads, Permission::UpdateLeadStatus, Permission::UpdatePaymentStatus])->create();
        $this->adminB = User::factory()->admin()->withCampaigns([$this->iva])->withDefaultPermissions()->create();
        $this->managerA = User::factory()->manager()->withCampaigns([$this->iva])->withPermissions([Permission::UpdateLeadStatus])->create();
        $this->managerB = User::factory()->manager()->withCampaigns([$this->iva])->withDefaultPermissions()->create();
        $this->solarManager = User::factory()->manager()->withCampaigns([$this->solar])->withDefaultPermissions()->create();

        $this->ivaLeadA = Lead::factory()->for_($this->iva, $this->adminA, $this->managerA)->create();
        $this->ivaLeadB = Lead::factory()->for_($this->iva, $this->adminB, $this->managerB)->create();
        $this->solarLead = Lead::factory()->for_($this->solar, null, $this->solarManager)->create();
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
            'campaign_id' => $this->iva->id,
            'assigned_to' => $this->managerA->id,
            ...$overrides,
        ];
    }

    /** Current values of a lead, for update payloads that should only change specific fields. */
    protected function currentPayload(Lead $lead, array $overrides = []): array
    {
        return [
            ...$lead->only('title', 'first_name', 'last_name', 'mobile', 'email', 'house_number', 'post_code', 'campaign_id', 'assigned_to', 'admin_id', 'lead_status_id', 'payment_status_id'),
            ...$overrides,
        ];
    }

    protected function listedLeadIds($response): array
    {
        return collect($response->viewData('page')['props']['leads']['data'])->pluck('id')->sort()->values()->all();
    }

    protected function ids(...$models): array
    {
        return collect($models)->pluck('id')->sort()->values()->all();
    }
}
