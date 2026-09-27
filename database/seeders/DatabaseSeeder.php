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
        // The default organization is seeded first so every organization-owned
        // row created afterwards is stamped with it. RBAC and settings follow.
        // The super admin is created by the first-run setup wizard, so it is
        // only seeded in local/testing (the test suite relies on the seeded
        // account).
        $this->call([
            OrganizationSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            SettingSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(UserSeeder::class);
        }
    }
}
