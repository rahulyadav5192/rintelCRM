<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || ($user->isBranchAdmin() && $user->hasPermission(Permission::ManageUsers));
    }

    public function view(User $user, User $target): bool
    {
        return User::query()->manageableBy($user)->whereKey($target->id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }

    /** Nobody can deactivate themselves (prevents locking out the last admin by accident). */
    public function toggleStatus(User $user, User $target): bool
    {
        return $user->id !== $target->id && $this->view($user, $target);
    }
}
