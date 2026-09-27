<?php

namespace App\Repositories\Backup;

use App\DTOs\Backup\BackupRunFilterDTO;
use App\Enums\BackupRunStatus;
use App\Enums\BackupRunTrigger;
use App\Enums\BackupRunType;
use App\Helpers\EloquentFilterHelper;
use App\Models\BackupRun;
use App\Repositories\Contracts\BackupRunRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BackupRunRepository implements BackupRunRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['created_at', 'started_at', 'completed_at', 'status', 'type'];

    public function findById(int $id): ?BackupRun
    {
        return BackupRun::query()->find($id);
    }

    public function buildFilterQuery(BackupRunFilterDTO $filters): Builder
    {
        $query = BackupRun::query();

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['file_name', 'disk', 'error_message'],
            [
                'status' => $filters->status?->value,
                'type' => $filters->type?->value,
            ],
            $query,
        );

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    public function paginate(BackupRunFilterDTO $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters)->paginate($perPage)->withQueryString();
    }

    public function create(array $data): BackupRun
    {
        $data['status'] ??= BackupRunStatus::PENDING;
        $data['trigger'] ??= BackupRunTrigger::SCHEDULED->value;

        return BackupRun::query()->create($data);
    }

    public function update(BackupRun $model, array $data): BackupRun
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(BackupRun $model): bool
    {
        return (bool) $model->delete();
    }

    public function latestOfType(BackupRunType $type, ?BackupRunStatus $status = null): ?BackupRun
    {
        return BackupRun::query()
            ->where('type', $type)
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->first();
    }

    public function hasCompletedSince(BackupRunType $type, Carbon $since): bool
    {
        return BackupRun::query()
            ->where('type', $type)
            ->where('status', BackupRunStatus::COMPLETED)
            ->where('completed_at', '>=', $since)
            ->exists();
    }

    public function isActive(BackupRunType $type): bool
    {
        return BackupRun::query()
            ->where('type', $type)
            ->whereIn('status', [BackupRunStatus::PENDING, BackupRunStatus::RUNNING])
            ->exists();
    }

    /**
     * @return Collection<int, BackupRun>
     */
    public function recent(int $limit = 10): Collection
    {
        return BackupRun::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
