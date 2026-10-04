<?php

namespace App\Repositories\Contracts;

use App\Models\ClockifyDeletedEntity;
use Illuminate\Support\Collection;

interface ClockifyDeletedEntityRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyDeletedEntity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyDeletedEntity;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyDeletedEntity $deleted, array $data): ClockifyDeletedEntity;

    public function delete(ClockifyDeletedEntity $deleted): bool;

    /**
     * Record a deletion once, keyed by its natural identity.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function upsert(array $attributes, array $values): ClockifyDeletedEntity;

    /**
     * Deletions for a workspace that have not been applied yet.
     *
     * @return Collection<int, ClockifyDeletedEntity>
     */
    public function unapplied(int $workspaceId, int $limit = 100): Collection;

    public function markApplied(ClockifyDeletedEntity $deleted): ClockifyDeletedEntity;
}
