<?php

namespace App\Repositories\Contracts;

use App\Enums\SyncJobStatus;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ClockifySyncJobRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifySyncJob;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifySyncJob;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifySyncJob $job, array $data): ClockifySyncJob;

    public function delete(ClockifySyncJob $job): bool;

    /**
     * Every job that belongs to a run, ordered for deterministic dispatch.
     *
     * @return Collection<int, ClockifySyncJob>
     */
    public function forRun(ClockifySyncRun|int $run): Collection;

    /**
     * The persisted resume checkpoint for a job (empty when none).
     *
     * @return array<string, mixed>
     */
    public function checkpoint(ClockifySyncJob $job): array;

    /**
     * Advance a job to the last completed page and add the page's counts.
     *
     * @param  array{processed?: int, created?: int, updated?: int, deleted?: int}  $counts
     */
    public function advancePage(ClockifySyncJob $job, int $page, array $counts = []): ClockifySyncJob;

    /**
     * Pending jobs for a run, in plan order (reference before facts).
     *
     * @return Collection<int, ClockifySyncJob>
     */
    public function pendingForRun(ClockifySyncRun|int $run): Collection;

    /**
     * Jobs a run may dispatch now: pending, or retry-scheduled whose backoff has
     * elapsed. Ordered by plan order (SYNC-13/SYNC-20).
     *
     * @return Collection<int, ClockifySyncJob>
     */
    public function dispatchableForRun(ClockifySyncRun|int $run): Collection;

    /**
     * Running jobs whose heartbeat is older than the given cut-off — their
     * worker was lost (SYNC-13).
     *
     * @return Collection<int, ClockifySyncJob>
     */
    public function staleRunning(CarbonInterface $before, int $limit = 100): Collection;

    /**
     * Cancel every non-final job of a run (cooperative cancellation).
     */
    public function cancelNonFinal(ClockifySyncRun|int $run): int;

    /**
     * Count a run's jobs in a given status.
     */
    public function countByStatus(ClockifySyncRun|int $run, SyncJobStatus $status): int;
}
