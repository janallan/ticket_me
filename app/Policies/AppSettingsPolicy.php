<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class AppSettingsPolicy
{
    /**
     * Determine whether the user can open App settings. It is covered by the `roles` permission.
     */
    public function view(User $user): bool
    {
        return $user->can(Permission::Roles->value);
    }

    /**
     * Determine whether the user can change App settings.
     */
    public function update(User $user): bool
    {
        return $user->can(Permission::Roles->value);
    }
}
