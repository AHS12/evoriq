<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyEntityChange;
use Illuminate\Support\Collection;

interface ClockifyEntityChangeRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyEntityChange;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyEntityChange;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyEntityChange $change, array $data): ClockifyEntityChange;

    public function delete(ClockifyEntityChange $change): bool;

    /**
     * Batch-upsert change rows (idempotent by the change-event natural key).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int;

    /**
     * Changes that have not been applied yet, oldest source first.
     *
     * @return Collection<int, ClockifyEntityChange>
     */
    public function unprocessed(int $limit = 100): Collection;

    public function markProcessed(ClockifyEntityChange $change): ClockifyEntityChange;
}
