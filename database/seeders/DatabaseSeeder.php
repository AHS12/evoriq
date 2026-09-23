<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // RBAC and settings are always seeded. The super admin is created by
        // the first-run setup wizard, so it is only seeded in local/testing
        // (the test suite relies on the seeded account).
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(UserSeeder::class);
        }
    }
}
