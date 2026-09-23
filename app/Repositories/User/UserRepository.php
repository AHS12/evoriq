<?php

namespace App\Repositories\User;

use App\DTOs\User\UserFilterDTO;
use App\Enums\UserRole;
use App\Helpers\EloquentFilterHelper;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['name', 'email', 'status', 'created_at'];

    /**
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(UserFilterDTO $filters, int $perPage = 15, array $relations = []): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters, $relations)->paginate($perPage);
    }

    /**
     * @param  array<int, string>  $relations
     * @return Builder<User>
     */
    public function buildFilterQuery(UserFilterDTO $filters, array $relations = []): Builder
    {
        $query = User::query()->with($relations);

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['name', 'email'],
            ['status' => $filters->status?->value],
            $query,
        );

        if ($filters->role !== null) {
            $query->role($filters->role);
        }

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?User
    {
        return User::query()->with($relations)->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    /**
     * @param  array<int, string>  $roles
     */
    public function assignRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        return $user->refresh();
    }

    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    public function hasSuperAdmin(): bool
    {
        return User::query()->role(UserRole::SUPER_ADMIN->value)->exists();
    }

    public function firstSuperAdmin(): ?User
    {
        return User::query()->role(UserRole::SUPER_ADMIN->value)->first();
    }

    public function deleteAll(): int
    {
        $users = User::query()->get();

        foreach ($users as $user) {
            $user->syncRoles([]);
            $user->delete();
        }

        return $users->count();
    }

    public function countAll(): int
    {
        return User::query()->count();
    }
}
