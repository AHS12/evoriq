<?php

namespace App\Repositories\Contracts;

use App\DTOs\Backup\BackupRunFilterDTO;
use App\Enums\BackupRunStatus;
use App\Enums\BackupRunType;
use App\Models\BackupRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

interface BackupRunRepositoryInterface
{
    public function findById(int $id): ?BackupRun;

    /**
     * @return Builder<BackupRun>
     */
    public function buildFilterQuery(BackupRunFilterDTO $filters): Builder;

    /**
     * @return LengthAwarePaginator<int, BackupRun>
     */
    public function paginate(BackupRunFilterDTO $filters, int $perPage = 10): LengthAwarePaginator;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BackupRun;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BackupRun $model, array $data): BackupRun;

    public function delete(BackupRun $model): bool;

    /**
     * The most recent run of the given type, optionally restricted to a status.
     */
    public function latestOfType(BackupRunType $type, ?BackupRunStatus $status = null): ?BackupRun;

    /**
     * Whether a run of the given type has completed since the given moment.
     */
    public function hasCompletedSince(BackupRunType $type, Carbon $since): bool;

    /**
     * Whether a run of the given type is currently pending or running.
     */
    public function isActive(BackupRunType $type): bool;

    /**
     * @return Collection<int, BackupRun>
     */
    public function recent(int $limit = 10): Collection;
}
