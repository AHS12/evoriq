<?php

namespace App\Repositories\Developer;

use App\DTOs\Developer\CommandRunFilterDTO;
use App\Enums\CommandRunStatus;
use App\Helpers\EloquentFilterHelper;
use App\Models\CommandRun;
use App\Repositories\Contracts\CommandRunRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommandRunRepository implements CommandRunRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['created_at', 'started_at', 'completed_at', 'status', 'action'];

    public function findById(int $id): ?CommandRun
    {
        return CommandRun::query()->find($id);
    }

    public function buildFilterQuery(CommandRunFilterDTO $filters): Builder
    {
        $query = CommandRun::query();

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['action', 'command'],
            [
                'status' => $filters->status?->value,
                'action' => $filters->action,
            ],
            $query,
        );

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    public function paginate(CommandRunFilterDTO $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters)->paginate($perPage);
    }

    public function create(array $data): CommandRun
    {
        $data['status'] ??= CommandRunStatus::PENDING;
        $data['progress'] ??= 0;

        return CommandRun::query()->create($data);
    }

    public function update(CommandRun $model, array $data): CommandRun
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(CommandRun $model): bool
    {
        return (bool) $model->delete();
    }

    public function recent(int $limit = 10): Collection
    {
        return CommandRun::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
