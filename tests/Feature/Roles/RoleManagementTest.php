<?php

namespace Tests\Feature\Roles;

use App\Enums\Permission;
use App\Livewire\Roles\RoleForm;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
    }

    public function test_role_managers_can_view_the_roles_pages(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $this->get(route('roles.index'))->assertOk()->assertSee('Manager');
        $this->get(route('roles.create'))->assertOk()->assertSee('New role - '.config('app.name'));
        $this->get(route('roles.edit', Role::findByName('Agent')))->assertOk()->assertSee('Edit role - '.config('app.name'));
    }

    public function test_users_without_the_roles_permission_cannot_access_the_roles_pages(): void
    {
        $this->actingAs($this->userWithRole('Agent'));

        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('roles.create'))->assertForbidden();
        $this->get(route('roles.edit', Role::findByName('Agent')))->assertForbidden();
    }

    public function test_the_roles_menu_is_only_shown_to_role_managers(): void
    {
        $this->actingAs($this->userWithRole('Admin'))
            ->get(route('dashboard'))
            ->assertSee(route('roles.index'));

        $this->actingAs($this->userWithRole('Agent'))
            ->get(route('dashboard'))
            ->assertDontSee(route('roles.index'));
    }

    public function test_a_role_can_be_created_with_permissions(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class)
            ->set('name', 'Supervisor')
            ->set('permissions', [Permission::Users->value])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $role = Role::findByName('Supervisor');

        $this->assertTrue($role->hasPermissionTo(Permission::Users->value));
        $this->assertFalse($role->hasPermissionTo(Permission::Roles->value));
    }

    public function test_a_role_can_be_renamed_and_its_permissions_changed(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        $manager = Role::findByName('Manager');

        Livewire::test(RoleForm::class, ['role' => $manager])
            ->assertSet('name', 'Manager')
            ->set('name', 'Team Lead')
            ->set('permissions', [Permission::Users->value])
            ->call('save')
            ->assertHasNoErrors();

        $manager->refresh();

        $this->assertSame('Team Lead', $manager->name);
        $this->assertTrue($manager->hasPermissionTo(Permission::Users->value));
    }

    public function test_role_names_must_be_unique(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class)
            ->set('name', 'Manager')
            ->call('save')
            ->assertHasErrors(['name' => 'unique']);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class)
            ->set('name', 'Supervisor')
            ->set('permissions', ['not-a-permission'])
            ->call('save')
            ->assertHasErrors(['permissions.0']);
    }

    public function test_users_cannot_grant_permissions_they_do_not_hold(): void
    {
        $roleManager = Role::create(['name' => 'Role Manager']);
        $roleManager->givePermissionTo(Permission::Roles->value);

        $this->actingAs($this->userWithRole('Role Manager'));

        Livewire::test(RoleForm::class)
            ->set('name', 'Supervisor')
            ->set('permissions', [Permission::Users->value, Permission::Roles->value])
            ->call('save')
            ->assertHasNoErrors();

        $supervisor = Role::findByName('Supervisor');

        $this->assertTrue($supervisor->hasPermissionTo(Permission::Roles->value));
        $this->assertFalse($supervisor->hasPermissionTo(Permission::Users->value));
    }

    public function test_users_cannot_revoke_permissions_they_do_not_hold(): void
    {
        $roleManager = Role::create(['name' => 'Role Manager']);
        $roleManager->givePermissionTo(Permission::Roles->value);

        $this->actingAs($this->userWithRole('Role Manager'));
        $this->userWithRole('Admin');

        Livewire::test(RoleForm::class, ['role' => Role::findByName('Admin')])
            ->set('permissions', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Role::findByName('Admin')->hasPermissionTo(Permission::Users->value));
    }

    public function test_role_management_cannot_be_removed_from_the_last_role_manager(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class, ['role' => Role::findByName('Admin')])
            ->set('permissions', [Permission::Users->value])
            ->call('save')
            ->assertHasErrors(['permissions']);

        $this->assertTrue(Role::findByName('Admin')->hasPermissionTo(Permission::Roles->value));
    }

    public function test_role_management_can_be_removed_when_another_role_manager_exists(): void
    {
        $roleManager = Role::create(['name' => 'Role Manager']);
        $roleManager->givePermissionTo(Permission::Roles->value);
        $this->userWithRole('Role Manager');

        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class, ['role' => Role::findByName('Admin')])
            ->set('permissions', [Permission::Users->value])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(Role::findByName('Admin')->hasPermissionTo(Permission::Roles->value));
    }

    public function test_a_role_without_users_can_be_deleted(): void
    {
        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class, ['role' => Role::findByName('Agent')])
            ->call('delete')
            ->assertHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $this->assertDatabaseMissing('roles', ['name' => 'Agent']);
    }

    public function test_a_role_with_users_cannot_be_deleted(): void
    {
        $this->userWithRole('Agent');

        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(RoleForm::class, ['role' => Role::findByName('Agent')])
            ->call('delete')
            ->assertHasErrors(['delete']);

        $this->assertDatabaseHas('roles', ['name' => 'Agent']);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }
}
