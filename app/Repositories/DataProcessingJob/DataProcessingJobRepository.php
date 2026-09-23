<?php

namespace App\Repositories\DataProcessingJob;

use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Enums\DataProcessingJobStatus;
use App\Helpers\EloquentFilterHelper;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
}
