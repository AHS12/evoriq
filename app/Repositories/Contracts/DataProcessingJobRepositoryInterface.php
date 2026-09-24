<?php

namespace App\Repositories\Contracts;

use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Models\DataProcessingJob;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface DataProcessingJobRepositoryInterface
{
    public function findById(int $id): ?DataProcessingJob;

    public function findByJobId(string $jobId): ?DataProcessingJob;

    /**
     * @return Builder<DataProcessingJob>
     */
    public function buildFilterQuery(DataProcessingJobFilterDTO $filters): Builder;

    /**
     * @return LengthAwarePaginator<int, DataProcessingJob>
     */
    public function paginate(DataProcessingJobFilterDTO $filters, int $perPage = 15): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DataProcessingJob;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DataProcessingJob $model, array $data): DataProcessingJob;

    public function delete(DataProcessingJob $model): bool;

    /**
     * Job counts grouped by status (plus a `total` key). A null user id counts
     * every user's jobs (for `data-processing.view.all`).
     *
     * @return array<string, int>
     */
    public function statusCounts(?int $userId = null): array;

    /**
     * The number of pending + processing jobs. A null user id counts every
     * user's jobs.
     */
    public function activeCount(?int $userId = null): int;

    /**
     * Processing jobs that started before the cutoff (likely stalled).
     *
     * @return Collection<int, DataProcessingJob>
     */
    public function staleProcessingBefore(CarbonInterface $cutoff): Collection;

    public function countCompletedBefore(Carbon $cutoff): int;

    /**
     * @return Collection<int, DataProcessingJob>
     */
    public function completedBefore(Carbon $cutoff, int $limit = 0): Collection;
}
