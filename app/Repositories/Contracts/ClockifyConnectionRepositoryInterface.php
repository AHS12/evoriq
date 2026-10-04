<?php

namespace App\Repositories\Contracts;

use App\DTOs\Connection\ConnectionFilterDTO;
use App\Models\ClockifyConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ClockifyConnectionRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?ClockifyConnection;

    /**
     * The most recently created active connection, for the current organization
     * or an explicit one (escapes the organization scope when provided).
     */
    public function findActive(?int $organizationId = null): ?ClockifyConnection;

    /**
     * Whether any connection exists (any status). Used to enforce the
     * single-connection MVP limit.
     */
    public function hasAny(): bool;

    /**
     * Every active connection the scheduler may sync; disabled/invalid
     * connections are excluded (CONN-06).
     *
     * @return Collection<int, ClockifyConnection>
     */
    public function allActive(?int $organizationId = null): Collection;

    /**
     * @return Builder<ClockifyConnection>
     */
    public function buildFilterQuery(ConnectionFilterDTO $filters): Builder;

    /**
     * @return LengthAwarePaginator<int, ClockifyConnection>
     */
    public function paginate(ConnectionFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * Every connection for a specific organization, bypassing the current scope.
     *
     * @return Collection<int, ClockifyConnection>
     */
    public function forOrganization(int|string $organizationId): Collection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyConnection;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyConnection $model, array $data): ClockifyConnection;

    public function delete(ClockifyConnection $model): bool;
}
