<?php

namespace App\Repositories\Contracts;

use App\DTOs\User\UserFilterDTO;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface UserRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(UserFilterDTO $filters, int $perPage = 15, array $relations = []): LengthAwarePaginator;

    /**
     * @param  array<int, string>  $relations
     * @return Builder<User>
     */
    public function buildFilterQuery(UserFilterDTO $filters, array $relations = []): Builder;

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User;

    /**
     * @param  array<int, string>  $roles
     */
    public function assignRoles(User $user, array $roles): User;

    public function delete(User $user): bool;

    public function hasSuperAdmin(): bool;

    /**
     * The first user holding the Super Admin role, if any.
     */
    public function firstSuperAdmin(): ?User;

    /**
     * Delete every user (detaching roles first).
     *
     * @return int The number of users deleted.
     */
    public function deleteAll(): int;

    public function countAll(): int;
}
