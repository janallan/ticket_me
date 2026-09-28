<?php

namespace Tests\Feature;

use App\Enums\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_missing_permissions_from_the_enum(): void
    {
        $this->artisan('app:acl-sync')
            ->expectsOutputToContain(Permission::Users->value)
            ->expectsOutputToContain('Permissions synced.')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            array_column(Permission::cases(), 'value'),
            PermissionModel::pluck('name')->all(),
        );
    }

    public function test_it_removes_permissions_that_are_no_longer_in_the_enum(): void
    {
        PermissionModel::create(['name' => 'obsolete']);

        $this->artisan('app:acl-sync')
            ->expectsOutputToContain('obsolete')
            ->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'obsolete']);
    }

    public function test_a_removed_permission_is_also_taken_off_every_role(): void
    {
        $this->artisan('app:acl-sync')->assertSuccessful();

        $role = Role::create(['name' => 'Support']);
        $role->givePermissionTo(PermissionModel::create(['name' => 'obsolete']));

        $this->artisan('app:acl-sync')->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'obsolete']);
        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_it_reports_when_permissions_are_already_in_sync(): void
    {
        $this->artisan('app:acl-sync')->assertSuccessful();

        $this->artisan('app:acl-sync')
            ->expectsOutputToContain('Permissions are already in sync.')
            ->assertSuccessful();
    }

    public function test_existing_role_assignments_are_kept(): void
    {
        $this->artisan('app:acl-sync')->assertSuccessful();

        $role = Role::create(['name' => 'Admin']);
        $role->givePermissionTo(Permission::Users->value);

        $this->artisan('app:acl-sync')->assertSuccessful();

        $this->assertTrue($role->fresh()->hasPermissionTo(Permission::Users->value));
    }
}
