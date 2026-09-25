<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the system roles and their permissions.
     */
    public function run(): void
    {
        $guard = config('auth.defaults.guard');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::firstOrCreate(
            ['name' => UserRole::SUPER_ADMIN->value, 'guard_name' => $guard],
            ['is_system' => true],
        );
        $superAdmin->syncPermissions(Permission::all());

        $admin = Role::firstOrCreate(
            ['name' => UserRole::ADMIN->value, 'guard_name' => $guard],
            ['is_system' => true],
        );
        $admin->syncPermissions(
            Permission::query()
                ->whereNotIn('name', [
                    'role.update',
                    'role.delete',
                    'settings.update',
                    'connection.credentials.update',
                    'audit.manage',
                ])
                ->get(),
        );
        $member = Role::firstOrCreate(
            ['name' => UserRole::MEMBER->value, 'guard_name' => $guard],
            ['is_system' => true],
        );
        $member->syncPermissions(
            Permission::query()
                ->whereIn('name', [
                    'report.view',
                    'report.create',
                    'report.update',
                    'report.export',
                    'export.create',
                    'data-processing.view',
                    'user.export',
                    'user.import',
                    'workspace.view',
                    'connection.view',
                    'sync.view',
                    'file.view',
                    'file.create',
                ])
                ->get(),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
