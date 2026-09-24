<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::Users->value);
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::Users->value);
    }

    /**
     * Determine whether the user can edit the given user. Users whose role grants
     * permissions the editor lacks cannot be edited, to prevent account takeover.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::Users->value) && $this->holdsPermissionsOf($user, $model);
    }

    /**
     * Determine whether the user can change the given user's role. Users cannot change their own role.
     */
    public function changeRole(User $user, User $model): bool
    {
        return $this->update($user, $model) && ! $user->is($model);
    }

    /**
     * Determine whether the user can deactivate or reactivate the given user. Users cannot deactivate themselves.
     */
    public function deactivate(User $user, User $model): bool
    {
        return $this->update($user, $model) && ! $user->is($model);
    }

    /**
     * Determine whether the user holds every permission the given user has.
     */
    private function holdsPermissionsOf(User $user, User $model): bool
    {
        $held = $user->getAllPermissions()->pluck('name');

        return $model->getAllPermissions()->pluck('name')->diff($held)->isEmpty();
    }
}
