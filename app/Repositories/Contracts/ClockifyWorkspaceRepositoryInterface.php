<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ClockifyWorkspaceRepositoryInterface
{
    /**
     * Idempotently upsert discovered workspaces for a connection, keyed by
     * organization + Clockify workspace id.
     *
     * @param  array<int, array<string, mixed>>  $workspaces
     * @return Collection<int, ClockifyWorkspace>
     */
    public function upsertMany(ClockifyConnection $connection, array $workspaces): Collection;

    /**
     * @return Collection<int, ClockifyWorkspace>
     */
    public function forConnection(ClockifyConnection $connection): Collection;

    public function findByClockifyId(int $connectionId, string $clockifyId): ?ClockifyWorkspace;

    /**
     * Mark exactly one workspace as active for a connection (all others off).
     */
    public function markActive(ClockifyConnection $connection, string $clockifyId): void;

    /**
     * Mark a workspace inactive (ENT-13): an upstream workspace deletion stops
     * syncing without destroying any rows (there is no `deleted_at` on the
     * workspace dimension).
     */
    public function markInactive(ClockifyConnection $connection, string $clockifyId): void;

    /**
     * Record the last successful change-feed scan time for a workspace
     * (SYNC-06; consumed by the incremental scheduler, SYNC-11).
     */
    public function advanceChangeFeedCursor(ClockifyWorkspace $workspace, CarbonInterface $at): ClockifyWorkspace;
}
