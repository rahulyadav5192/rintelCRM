<?php

namespace App\Policies;

use App\Models\User;

/** User management (admins and managers alike) is reserved for Super Admins. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }

    /** Nobody can deactivate themselves (prevents locking out the last Super Admin by accident). */
    public function toggleStatus(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && $user->id !== $target->id;
    }
}
