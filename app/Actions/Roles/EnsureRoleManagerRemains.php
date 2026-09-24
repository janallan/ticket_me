<?php

namespace App\Actions\Roles;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role;

/**
 * Guards against changes that would leave no active user able to manage roles.
 */
class EnsureRoleManagerRemains
{
    /**
     * Ensure the role can be saved with the given permissions.
     *
     * @param  list<string>  $permissions
     *
     * @throws ValidationException
     */
    public function forRole(Role $role, array $permissions, string $errorKey = 'permissions'): void
    {
        $removesRoleManagement = $role->hasPermissionTo(Permission::Roles->value)
            && ! in_array(Permission::Roles->value, $permissions, true);

        if (! $removesRoleManagement) {
            return;
        }

        $this->ensureOtherManagersExist(
            fn (Builder $query) => $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereKey($role->getKey())),
            $errorKey,
        );
    }

    /**
     * Ensure the user can be given the new role, or be deactivated.
     *
     * @throws ValidationException
     */
    public function forUser(User $user, ?RoleContract $newRole, bool $deactivating, string $errorKey): void
    {
        $canManageRoles = $user->isActive() && $user->can(Permission::Roles->value);

        if (! $canManageRoles) {
            return;
        }

        $keepsRoleManagement = ! $deactivating
            && $newRole !== null
            && $newRole->hasPermissionTo(Permission::Roles->value);

        if ($keepsRoleManagement) {
            return;
        }

        $this->ensureOtherManagersExist(
            fn (Builder $query) => $query->whereKeyNot($user->getKey()),
            $errorKey,
        );
    }

    /**
     * @param  callable(Builder<User>): mixed  $excluding
     *
     * @throws ValidationException
     */
    private function ensureOtherManagersExist(callable $excluding, string $errorKey): void
    {
        $query = User::query()->active()->permission(Permission::Roles->value);

        $excluding($query);

        if (! $query->exists()) {
            throw ValidationException::withMessages([
                $errorKey => __('At least one active user must be able to manage roles.'),
            ]);
        }
    }
}
