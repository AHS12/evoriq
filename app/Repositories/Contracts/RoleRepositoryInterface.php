<?php

namespace App\Repositories\Contracts;

use App\DTOs\Role\RoleFilterDTO;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(RoleFilterDTO $filters, int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $relations
     * @return Builder<Role>
     */
    public function buildFilterQuery(RoleFilterDTO $filters, array $relations = []): Builder;

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?Role;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role;

    public function delete(Role $role): bool;

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): Role;

    /**
     * Resolve the given permission names to those that may be assigned,
     * excluding system permissions unless the role is itself a system role.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function assignablePermissionNames(array $names, bool $includeSystem): array;

    public function isAssignedTo(Role $role, int $userId): bool;

    /**
     * @return Collection<int, Permission>
     */
    public function permissionCatalog(): Collection;
}
