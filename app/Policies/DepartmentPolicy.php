<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    /**
     * Determine whether the user can view the list of departments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::Departments->value);
    }

    /**
     * Determine whether the user can create departments.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::Departments->value);
    }

    /**
     * Determine whether the user can edit the department and its members.
     */
    public function update(User $user, Department $department): bool
    {
        return $user->can(Permission::Departments->value);
    }

    /**
     * Determine whether the user can delete the department.
     */
    public function delete(User $user, Department $department): bool
    {
        return $user->can(Permission::Departments->value);
    }
}
