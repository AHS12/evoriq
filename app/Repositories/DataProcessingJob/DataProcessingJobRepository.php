<?php

namespace App\Repositories\DataProcessingJob;

use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Enums\DataProcessingJobStatus;
use App\Helpers\EloquentFilterHelper;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DataProcessingJobRepository implements DataProcessingJobRepositoryInterface
{
    /**
     * Columns that list endpoints may sort by.
     *
     * @var list<string>
     */
    private const ORDERABLE = ['created_at', 'completed_at', 'started_at', 'status', 'type', 'file_name'];

    public function findById(int $id): ?DataProcessingJob
    {
        return DataProcessingJob::query()->find($id);
    }

    public function findByJobId(string $jobId): ?DataProcessingJob
    {
        return DataProcessingJob::query()->where('job_id', $jobId)->first();
    }

    public function findManyByIds(array $ids): Collection
    {
        if ($ids === []) {
            return new Collection;
        }

        return DataProcessingJob::query()
            ->whereIn('id', array_map(static fn (int|string $id): int => (int) $id, $ids))
            ->get();
    }

    public function recentDuplicateExists(DataProcessingJob $job, CarbonInterface $since): bool
    {
        if ($job->entity_type === null) {
            return false;
        }

        return DataProcessingJob::query()
            ->whereKeyNot($job->getKey())
            ->where('type', $job->type)
            ->where('entity_type', $job->entity_type)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    public function buildFilterQuery(DataProcessingJobFilterDTO $filters): Builder
    {
        $query = DataProcessingJob::query();

        $query = EloquentFilterHelper::applyFilters(
            $filters->search,
            ['job_id', 'original_file_name', 'entity_type'],
            [
                'type' => $filters->type?->value,
                'status' => $filters->status?->value,
                'entity_type' => $filters->entityType?->value,
                'user_id' => $filters->userId,
            ],
            $query,
        );

        $orderBy = in_array($filters->orderBy, self::ORDERABLE, true) ? $filters->orderBy : 'created_at';
        $orderDirection = strtolower($filters->orderDirection) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($orderBy, $orderDirection);
    }

    public function paginate(DataProcessingJobFilterDTO $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildFilterQuery($filters)->paginate($perPage);
    }

    public function create(array $data): DataProcessingJob
    {
        $data['job_id'] ??= (string) Str::uuid();
        $data['status'] ??= DataProcessingJobStatus::PENDING;
        $data['filters'] ??= [];

        return DataProcessingJob::query()->create($data);
    }

    public function update(DataProcessingJob $model, array $data): DataProcessingJob
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(DataProcessingJob $model): bool
    {
        return (bool) $model->delete();
    }

    public function statusCounts(?int $userId = null): array
    {
        $counts = DataProcessingJob::query()
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $result = [];

        foreach (DataProcessingJobStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        $result['total'] = array_sum($result);

        return $result;
    }

    public function activeCount(?int $userId = null): int
    {
        return DataProcessingJob::query()
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->active()
            ->count();
    }

    public function failedRecentCount(?int $userId, CarbonInterface $since): int
    {
        return DataProcessingJob::query()
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->where('status', DataProcessingJobStatus::FAILED)
            ->where('completed_at', '>=', $since)
            ->count();
    }

    public function activeJobs(?int $userId = null, int $limit = 5): Collection
    {
        return DataProcessingJob::query()
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId))
            ->active()
            ->orderBy('created_at')
            ->limit(max(1, $limit))
            ->get();
    }

    public function pipelineRevision(?int $userId = null): string
    {
        $query = DataProcessingJob::query()
            ->when($userId !== null, fn (Builder $builder): Builder => $builder->where('user_id', $userId));

        $count = (clone $query)->count();
        $latest = (clone $query)->max('updated_at');

        return $count.'|'.($latest !== null ? (string) $latest : '0');
    }

    public function staleProcessingBefore(CarbonInterface $cutoff): Collection
    {
        return DataProcessingJob::query()
            ->where('status', DataProcessingJobStatus::PROCESSING)
            ->where(function (Builder $query) use ($cutoff): void {
                $query->where('last_heartbeat_at', '<', $cutoff)
                    ->orWhere(function (Builder $query) use ($cutoff): void {
                        $query->whereNull('last_heartbeat_at')
                            ->whereNotNull('started_at')
                            ->where('started_at', '<', $cutoff);
                    });
            })
            ->get();
    }

    public function countCompletedBefore(Carbon $cutoff): int
    {
        return DataProcessingJob::query()
            ->where('status', DataProcessingJobStatus::COMPLETED)
            ->where('completed_at', '<', $cutoff)
            ->count();
    }

    public function completedBefore(Carbon $cutoff, int $limit = 0): Collection
    {
        $query = DataProcessingJob::query()
            ->where('status', DataProcessingJobStatus::COMPLETED)
            ->where('completed_at', '<', $cutoff)
            ->orderBy('completed_at');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function finishedRunsSince(CarbonInterface $since): Collection
    {
        return DataProcessingJob::query()
            ->whereIn('status', [DataProcessingJobStatus::COMPLETED, DataProcessingJobStatus::FAILED])
            ->where('completed_at', '>=', $since)
            ->get(['id', 'status', 'started_at', 'completed_at', 'success_count', 'failure_reason']);
    }

    public function runsCreatedSince(CarbonInterface $since): int
    {
        return DataProcessingJob::query()
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function recentRuns(int $limit): Collection
    {
        return DataProcessingJob::query()
            ->orderByDesc('created_at')
            ->limit(max(1, $limit))
            ->get();
    }

    public function recentFailures(int $limit): Collection
    {
        return DataProcessingJob::query()
            ->where('status', DataProcessingJobStatus::FAILED)
            ->orderByDesc('completed_at')
            ->limit(max(1, $limit))
            ->get();
    }
}
