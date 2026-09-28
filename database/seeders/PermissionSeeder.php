<?php

namespace Database\Seeders;

use App\Actions\Permissions\SyncPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Create a permission for every case of the Permission enum, but only when the permissions table
     * is empty. Existing databases are kept in step with `php artisan app:acl-sync` instead.
     */
    public function run(SyncPermissions $syncPermissions): void
    {
        if (Permission::query()->exists()) {
            return;
        }

        $syncPermissions();
    }
}
