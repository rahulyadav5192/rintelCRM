<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    /** Admins can browse the campaigns assigned to them (read only). */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin() || ($user->isAdmin() && $user->canAccessCampaign($campaign->id));
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->isSuperAdmin();
    }
}
