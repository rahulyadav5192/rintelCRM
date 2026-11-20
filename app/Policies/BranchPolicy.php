<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

/** Branch administration is reserved for Super Admins. */
class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->isSuperAdmin();
    }
}
