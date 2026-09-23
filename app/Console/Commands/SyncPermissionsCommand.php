<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Registry\PermissionRegistry;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions from the registry and grant them to the super admin role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::upsert(
            PermissionRegistry::getAllModulePermissions(),
            ['name', 'guard_name'],
            ['group', 'description', 'is_system', 'updated_at'],
        );

        $superAdmin = Role::query()
            ->where('name', UserRole::SUPER_ADMIN->value)
            ->where('guard_name', config('auth.defaults.guard'))
            ->first();

        $superAdmin?->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info(sprintf('Permissions synchronized: %d total.', Permission::count()));

        return self::SUCCESS;
    }
}
