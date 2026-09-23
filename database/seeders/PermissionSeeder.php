<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Registry\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Seed the permissions declared in the permission registry.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::upsert(
            PermissionRegistry::getAllModulePermissions(),
            ['name', 'guard_name'],
            ['group', 'description', 'is_system', 'updated_at'],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
