<?php

namespace Database\Seeders;

use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The default roles and their permissions.
     *
     * @var array<string, list<Permission>>
     */
    private const DEFAULTS = [
        'Admin' => [
            Permission::Users,
            Permission::Roles,
            Permission::Tickets,
            Permission::TicketsAll,
            Permission::Departments,
            Permission::TicketStatuses,
            Permission::TicketPriorities,
        ],
        'Manager' => [Permission::Tickets, Permission::TicketsAll],
        'Agent' => [Permission::Tickets],
    ];

    /**
     * Create the default roles, but only when the roles table is empty, so seeding never changes
     * roles on an existing database.
     */
    public function run(): void
    {
        if (Role::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $name => $permissions) {
            Role::create(['name' => $name, 'guard_name' => 'web'])
                ->syncPermissions(array_column($permissions, 'value'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
