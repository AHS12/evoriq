<?php

namespace App\Repositories\Connection;

use App\DTOs\Connection\ConnectionFilterDTO;
use App\Enums\ConnectionStatus;
use App\Helpers\EloquentFilterHelper;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClockifyConnectionRepository implements ClockifyConnectionRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['name', 'region', 'status', 'created_at', 'last_verified_at'];

    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int $id, array $relations = []): ?ClockifyConnection
    {
        return ClockifyConnection::query()->with($relations)->find($id);
    }

    public function findActive(?int $organizationId = null): ?ClockifyConnection
    {
        $query = ClockifyConnection::query()
            ->where('status', ConnectionStatus::ACTIVE);

        if ($organizationId !== null) {
            $query->withoutOrganizationScope()
                ->where('organization_id', $organizationId);
        }

        return $query->latest('id')->first();
    }

    public function buildFilterQuery(ConnectionFilterDTO $filters): Builder
    {
        $query = ClockifyConnection::query();

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['name', 'subdomain', 'workspace_id'],
            [
                'status' => $filters->status?->value,
                'region' => $filters->region?->value,
            ],
            $query,
        );

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    public function paginate(ConnectionFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters)->paginate($perPage);
    }

    public function forOrganization(int|string $organizationId): Collection
    {
        return ClockifyConnection::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->get();
    }

    public function create(array $data): ClockifyConnection
    {
        return ClockifyConnection::query()->create($data);
    }

    public function update(ClockifyConnection $model, array $data): ClockifyConnection
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(ClockifyConnection $model): bool
    {
        return (bool) $model->delete();
    }
}
