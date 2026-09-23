<?php

namespace App\Repositories\Contracts;

use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Models\DataProcessingJob;
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

    public function countCompletedBefore(Carbon $cutoff): int;

    /**
     * @return Collection<int, DataProcessingJob>
     */
    public function completedBefore(Carbon $cutoff, int $limit = 0): Collection;
}
