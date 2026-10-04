<?php

namespace App\Repositories\Sync;

use App\Models\ClockifyEntityChange;
use App\Repositories\Contracts\ClockifyEntityChangeRepositoryInterface;
use Illuminate\Support\Collection;

class ClockifyEntityChangeRepository implements ClockifyEntityChangeRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyEntityChange
    {
        return ClockifyEntityChange::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyEntityChange
    {
        return ClockifyEntityChange::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyEntityChange $change, array $data): ClockifyEntityChange
    {
        $change->update($data);

        return $change->refresh();
    }

    public function delete(ClockifyEntityChange $change): bool
    {
        return (bool) $change->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function upsertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $prepared = array_map(static function (array $row): array {
            if (isset($row['raw_data']) && is_array($row['raw_data'])) {
                $row['raw_data'] = json_encode($row['raw_data']);
            }

            return $row;
        }, $rows);

        return ClockifyEntityChange::query()->upsert(
            $prepared,
            ['organization_id', 'workspace_id', 'entity_type', 'clockify_id', 'change_type', 'source_at'],
            ['detected_at', 'raw_data'],
        );
    }

    /**
     * @return Collection<int, ClockifyEntityChange>
     */
    public function unprocessed(int $limit = 100): Collection
    {
        return ClockifyEntityChange::query()
            ->whereNull('processed_at')
            ->orderBy('source_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function markProcessed(ClockifyEntityChange $change): ClockifyEntityChange
    {
        $change->update(['processed_at' => now()]);

        return $change->refresh();
    }
}
