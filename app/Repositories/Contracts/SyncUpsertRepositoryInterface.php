<?php

namespace App\Repositories\Contracts;

use App\DTOs\Sync\UpsertCounts;
use App\Models\ClockifyWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * The idempotent-write contract every synced entity repository implements
 * (SYNC-08). The natural key is `(organization_id, workspace_id, clockify_id)`,
 * so re-applying a page overwrites rather than duplicates.
 */
interface SyncUpsertRepositoryInterface
{
    /**
     * Upsert a single row by its Clockify id.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByClockifyId(ClockifyWorkspace $workspace, array $attributes): Model;

    /**
     * Upsert a batch of rows, reporting accurate create/update/unchanged counts.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function upsertMany(ClockifyWorkspace $workspace, iterable $rows): UpsertCounts;

    /**
     * Apply an upstream deletion by soft-deleting the row (SYNC-07). When the
     * table has no `deleted_at` column the row is hard-deleted instead. Returns
     * `true` when a row was affected.
     */
    public function softDeleteByClockifyId(ClockifyWorkspace $workspace, string $clockifyId): bool;

    /**
     * Remove a row outright — used for join tables whose upstream deletion drops
     * the row rather than soft-deleting it (SYNC-07). Returns `true` when a row
     * was affected.
     */
    public function deleteByClockifyId(ClockifyWorkspace $workspace, string $clockifyId): bool;
}
