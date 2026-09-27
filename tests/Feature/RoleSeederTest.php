<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_default_roles(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        $this->assertEqualsCanonicalizing(['Admin', 'Manager', 'Agent'], Role::pluck('name')->all());

        $admin = Role::findByName('Admin');

        $this->assertTrue($admin->hasPermissionTo(Permission::Users->value));
        $this->assertTrue($admin->hasPermissionTo(Permission::Roles->value));
        $this->assertTrue($admin->hasPermissionTo(Permission::Departments->value));
        $this->assertTrue($admin->hasPermissionTo(Permission::TicketStatuses->value));
        $this->assertTrue($admin->hasPermissionTo(Permission::TicketPriorities->value));
        $this->assertCount(0, Role::findByName('Manager')->permissions);
        $this->assertCount(0, Role::findByName('Agent')->permissions);
    }

    public function test_reseeding_keeps_customized_roles(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Role::findByName('Manager')->givePermissionTo(Permission::Users->value);

        $this->seed(RoleSeeder::class);

        $this->assertTrue(Role::findByName('Manager')->hasPermissionTo(Permission::Users->value));
        $this->assertSame(3, Role::count());
    }

    public function test_the_default_admin_user_is_given_the_admin_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertTrue($admin->hasRole('Admin'));
        $this->assertSame('IT', $admin->defaultDepartment?->name);
        $this->assertTrue($admin->departments->contains($admin->default_department_id));
    }
}
