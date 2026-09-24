<?php

namespace Database\Seeders;

use App\Actions\Permissions\SyncPermissions;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Sync the permissions table with the Permission enum.
     */
    public function run(SyncPermissions $syncPermissions): void
    {
        $syncPermissions();
    }
}
