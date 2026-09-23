<?php

namespace App\Repositories\Role;

use App\DTOs\Role\RoleFilterDTO;
use App\Helpers\EloquentFilterHelper;
use App\Models\Permission;
use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['name', 'created_at', 'permissions_count', 'users_count'];

    /**
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, Role>
     */
    public function paginate(RoleFilterDTO $filters, int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters, $relations)->paginate($perPage);
    }

    /**
     * @param  array<int, string>  $relations
     * @return Builder<Role>
     */
    public function buildFilterQuery(RoleFilterDTO $filters, array $relations = []): Builder
    {
        $query = Role::query()
            ->with($relations)
            ->withCount(['permissions', 'users']);

        $query = EloquentFilterHelper::applySearchFilters($filters->search, $query, ['name']);

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'name';
        $orderDirection = strtolower($filters->orderDirection) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?Role
    {
        return Role::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role->refresh();
    }

    public function delete(Role $role): bool
    {
        return (bool) $role->delete();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);

        return $role->refresh()->load('permissions');
    }

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function assignablePermissionNames(array $names, bool $includeSystem): array
    {
        if ($names === []) {
            return [];
        }

        return Permission::query()
            ->whereIn('name', $names)
            ->when(! $includeSystem, fn (Builder $query): Builder => $query->where('is_system', false))
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->values()
            ->all();
    }

    public function isAssignedTo(Role $role, int $userId): bool
    {
        return $role->users()->whereKey($userId)->exists();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissionCatalog(): Collection
    {
        return Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }
}
