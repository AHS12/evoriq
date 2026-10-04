<?php

namespace App\Repositories\Contracts;

use App\Enums\SyncEntityType;
use App\Models\ClockifyRawRecord;

interface ClockifyRawRecordRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyRawRecord;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyRawRecord;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyRawRecord $record, array $data): ClockifyRawRecord;

    public function delete(ClockifyRawRecord $record): bool;

    /**
     * The stored payload for an entity, if any.
     */
    public function find(int $workspaceId, SyncEntityType $entityType, string $clockifyId): ?ClockifyRawRecord;

    /**
     * Persist (or refresh) the latest payload for an entity.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function upsert(array $attributes, array $values): ClockifyRawRecord;

    /**
     * The stored payload hashes for the given entity ids, keyed by Clockify id.
     *
     * @param  array<int, string>  $clockifyIds
     * @return array<string, string>
     */
    public function existingHashes(int $workspaceId, SyncEntityType $entityType, array $clockifyIds): array;

    /**
     * Batch upsert DB-ready rows in a single statement; returns the row count.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;

    /**
     * Delete every raw record for a workspace (optionally one entity type).
     */
    public function deleteForWorkspace(int $workspaceId, ?SyncEntityType $entityType = null): int;

    /**
     * Delete raw records fetched more than `$days` ago; `0` is a no-op.
     */
    public function prune(int $days): int;
}
