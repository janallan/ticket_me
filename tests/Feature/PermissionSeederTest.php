<?php

namespace Tests\Feature;

use App\Enums\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as PermissionModel;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_permission_for_every_enum_case(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertEqualsCanonicalizing(
            array_column(Permission::cases(), 'value'),
            PermissionModel::pluck('name')->all(),
        );
    }

    public function test_it_can_run_more_than_once_without_duplicates(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertSame(count(Permission::cases()), PermissionModel::count());
    }

    public function test_it_leaves_an_existing_permissions_table_alone(): void
    {
        PermissionModel::create(['name' => 'obsolete']);

        $this->seed(PermissionSeeder::class);

        $this->assertSame(['obsolete'], PermissionModel::pluck('name')->all());
    }
}
