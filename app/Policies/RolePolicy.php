<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view the list of roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::Roles->value);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::Roles->value);
    }

    /**
     * Determine whether the user can edit the role and its permissions.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can(Permission::Roles->value);
    }

    /**
     * Determine whether the user can delete the role. Roles that still have users assigned cannot be deleted.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permission::Roles->value) && ! $role->users()->exists();
    }
}
