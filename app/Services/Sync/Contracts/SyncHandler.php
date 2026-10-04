<?php

namespace App\Services\Sync\Contracts;

use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;
use App\Services\Sync\SyncContext;

/**
 * The per-entity ingestion contract (SYNC-04, formalized by ENT-00). A handler
 * declares its endpoint/mapping/deletion policy; the runner drives fetch → raw →
 * map → idempotent upsert and owns the checkpointing.
 */
interface SyncHandler
{
    public function entityType(): SyncEntityType;

    public function phase(): SyncPhase;

    /**
     * Fetch a single page of raw upstream items.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function fetchPage(SyncContext $context, int $page): iterable;

    /**
     * Map a raw upstream item to normalized, persistable attributes.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public function map(array $raw, SyncContext $context): array;

    public function repository(): SyncUpsertRepositoryInterface;

    /**
     * Apply a deletion for an entity id (SYNC-07); a no-op until then.
     */
    public function delete(SyncContext $context, string $clockifyId): void;
}
