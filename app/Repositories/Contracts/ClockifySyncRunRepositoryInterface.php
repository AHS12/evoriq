<?php

namespace App\Repositories\Contracts;

use App\Enums\SyncPriority;
use App\Models\ClockifySyncRun;
use Illuminate\Support\Collection;

interface ClockifySyncRunRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifySyncRun;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifySyncRun;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifySyncRun $run, array $data): ClockifySyncRun;

    public function delete(ClockifySyncRun $run): bool;

    /**
     * The most recent runs for a connection.
     *
     * @return Collection<int, ClockifySyncRun>
     */
    public function forConnection(int $connectionId, int $limit = 20): Collection;

    /**
     * The most recent non-final run, if any (import concurrency guard).
     */
    public function activeRun(): ?ClockifySyncRun;

    /**
     * Non-final runs that have dispatchable work (a pending job, or a retry
     * whose backoff has elapsed) — the safety net for budget-parked runs
     * (SYNC-13/SYNC-20).
     *
     * @return Collection<int, ClockifySyncRun>
     */
    public function resumable(): Collection;

    /**
     * Whether any non-final run has a strictly higher priority than the given
     * level (starvation avoidance, SYNC-20).
     */
    public function hasActiveHigherPriority(SyncPriority $priority): bool;

    /**
     * Recompute a run's aggregate counters from its jobs and persist them.
     */
    public function countsRecalculate(ClockifySyncRun $run): ClockifySyncRun;
}
