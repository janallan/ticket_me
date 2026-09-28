<?php

namespace Database\Seeders;

use App\Actions\Users\SyncUserDepartments;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder only fills tables that are empty, and the admin
     * account is only created when there are no users yet.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            TicketSettingsSeeder::class,
        ]);

        if (User::query()->exists()) {
            return;
        }

        $admin = new User;
        $admin->forceFill([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ])->save();

        if (Role::query()->where('name', 'Admin')->exists()) {
            $admin->assignRole('Admin');
        }

        $department = Department::where('name', 'IT')->first() ?? Department::query()->orderBy('name')->first();

        if ($department !== null) {
            app(SyncUserDepartments::class)($admin, [$department->id], $department->id);
        }
    }
}
