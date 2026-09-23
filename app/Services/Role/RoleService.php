<?php

namespace App\Services\Role;

use App\DTOs\Role\RoleDTO;
use App\DTOs\Role\RoleFilterDTO;
use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    public function __construct(
        protected RoleRepositoryInterface $roles,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(RoleFilterDTO $filters): LengthAwarePaginator
    {
        return $this->roles->paginate($filters, $filters->perPage, ['permissions']);
    }

    public function find(int $id): ?Role
    {
        return $this->roles->findById($id, ['permissions']);
    }

    /**
     * The read-only catalog of permissions available to the role matrix.
     *
     * @return Collection<int, Permission>
     */
    public function permissionCatalog(): Collection
    {
        return $this->roles->permissionCatalog();
    }

    /**
     * Create a custom role and sync its permissions.
     */
    public function create(RoleDTO $dto): Role
    {
        return DB::transaction(function () use ($dto): Role {
            $role = $this->roles->create([
                'name' => $dto->name,
                'guard_name' => config('auth.defaults.guard'),
                'is_system' => false,
            ]);

            return $this->syncPermissions($role, $dto->permissions);
        });
    }

    /**
     * Update a role's name (custom roles only) and permissions.
     */
    public function update(Role $role, RoleDTO $dto): Role
    {
        $this->guardAgainstModifyingSuperAdmin($role);

        return DB::transaction(function () use ($role, $dto): Role {
            if (! $role->is_system) {
                $role = $this->roles->update($role, ['name' => $dto->name]);
            }

            $this->guardAgainstSelfLockout($role, $dto->permissions);

            return $this->syncPermissions($role, $dto->permissions);
        });
    }

    public function delete(Role $role): bool
    {
        if ($role->is_system) {
            throw new AuthorizationException(__('System roles cannot be deleted.'));
        }

        return DB::transaction(fn (): bool => $this->roles->delete($role));
    }

    /**
     * Filter permissions to the assignable set and persist them.
     *
     * @param  array<int, string>  $permissions
     */
    protected function syncPermissions(Role $role, array $permissions): Role
    {
        $assignable = $this->roles->assignablePermissionNames($permissions, $role->is_system);

        $role = $this->roles->syncPermissions($role, $assignable);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    protected function guardAgainstModifyingSuperAdmin(Role $role): void
    {
        if ($role->name === UserRole::SUPER_ADMIN->value) {
            throw new AuthorizationException(__('The Super Admin role cannot be modified.'));
        }
    }

    /**
     * Prevent a non super admin from removing their own ability to manage roles.
     *
     * @param  array<int, string>  $permissions
     */
    protected function guardAgainstSelfLockout(Role $role, array $permissions): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User || $actor->isSuperAdmin()) {
            return;
        }

        if (in_array('role.update', $permissions, true)) {
            return;
        }

        if ($this->roles->isAssignedTo($role, $actor->id)) {
            throw new AuthorizationException(__('You cannot remove your own ability to manage roles.'));
        }
    }
}
