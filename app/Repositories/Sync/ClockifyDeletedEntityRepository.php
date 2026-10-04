<?php

namespace App\Repositories\Sync;

use App\Models\ClockifyDeletedEntity;
use App\Repositories\Contracts\ClockifyDeletedEntityRepositoryInterface;
use Illuminate\Support\Collection;

class ClockifyDeletedEntityRepository implements ClockifyDeletedEntityRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyDeletedEntity
    {
        return ClockifyDeletedEntity::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyDeletedEntity
    {
        return ClockifyDeletedEntity::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyDeletedEntity $deleted, array $data): ClockifyDeletedEntity
    {
        $deleted->update($data);

        return $deleted->refresh();
    }

    public function delete(ClockifyDeletedEntity $deleted): bool
    {
        return (bool) $deleted->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function upsert(array $attributes, array $values): ClockifyDeletedEntity
    {
        return ClockifyDeletedEntity::query()
            ->withoutOrganizationScope()
            ->updateOrCreate($attributes, $values);
    }

    /**
     * @return Collection<int, ClockifyDeletedEntity>
     */
    public function unapplied(int $workspaceId, int $limit = 100): Collection
    {
        return ClockifyDeletedEntity::query()
            ->withoutOrganizationScope()
            ->where('workspace_id', $workspaceId)
            ->whereNull('applied_at')
            ->orderBy('deleted_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function markApplied(ClockifyDeletedEntity $deleted): ClockifyDeletedEntity
    {
        $deleted->update(['applied_at' => now()]);

        return $deleted->refresh();
    }
}
