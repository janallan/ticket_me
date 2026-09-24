<?php

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The default roles and their permissions. Roles are only created when missing,
     * so re-seeding never overwrites changes made from the Roles screen.
     *
     * @var array<string, list<Permission>>
     */
    private const DEFAULTS = [
        'Admin' => [Permission::Users, Permission::Roles],
        'Manager' => [],
        'Agent' => [],
    ];

    /**
     * Seed the default roles.
     */
    public function run(): void
    {
        foreach (self::DEFAULTS as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions(array_column($permissions, 'value'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
