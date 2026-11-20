<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Re-runs the central visibility scope for this single record (prevents IDOR via URL IDs). */
    public function view(User $user, Lead $lead): bool
    {
        return Lead::query()->visibleTo($user)->whereKey($lead->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CreateLeads);
    }

    /** Any kind of modification (details, assignment, status, payment). Field-level checks live in LeadService. */
    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead) && (
            $user->hasPermission(Permission::EditLeads)
            || $user->hasPermission(Permission::AssignLeads)
            || $user->hasPermission(Permission::UpdateLeadStatus)
            || $user->hasPermission(Permission::UpdatePaymentStatus)
        );
    }

    public function addNote(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermission(Permission::DeleteLeads) && $this->view($user, $lead);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission(Permission::ExportLeads);
    }
}
