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

        $this->assertEqualsCanonicalizing(array_column(Permission::cases(), 'value'), $admin->permissions->pluck('name')->all());
        $this->assertEqualsCanonicalizing([Permission::Tickets->value, Permission::TicketsAll->value], Role::findByName('Manager')->permissions->pluck('name')->all());
        $this->assertSame([Permission::Tickets->value], Role::findByName('Agent')->permissions->pluck('name')->all());
    }

    public function test_reseeding_keeps_customized_roles(): void
    {
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);

        Role::findByName('Manager')->givePermissionTo(Permission::Users->value);

        $this->seed(RoleSeeder::class);

        $this->assertTrue(Role::findByName('Manager')->hasPermissionTo(Permission::Users->value));
        $this->assertSame(3, Role::count());
    }

    public function test_no_default_roles_are_created_when_any_role_exists(): void
    {
        $this->seed(PermissionSeeder::class);
        Role::create(['name' => 'Support']);

        $this->seed(RoleSeeder::class);

        $this->assertSame(['Support'], Role::pluck('name')->all());
    }

    public function test_the_admin_account_is_only_created_when_there_are_no_users(): void
    {
        User::factory()->create(['email' => 'someone@example.com']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'admin@example.com']);
        $this->assertSame(1, User::count());
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
