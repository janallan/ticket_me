<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $admin = User::firstOrNew(['email' => 'admin@example.com']);

        if (! $admin->exists) {
            $admin->forceFill([
                'name' => 'Admin',
                'password' => 'password',
                'email_verified_at' => now(),
            ])->save();
        }

        if ($admin->roles()->doesntExist()) {
            $admin->assignRole('Admin');
        }
    }
}
