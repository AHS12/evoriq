<?php

namespace App\Repositories\Sync;

use App\Enums\SyncEntityType;
use App\Models\ClockifyRawRecord;
use App\Repositories\Contracts\ClockifyRawRecordRepositoryInterface;

class ClockifyRawRecordRepository implements ClockifyRawRecordRepositoryInterface
{
    /**
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $relations = []): ?ClockifyRawRecord
    {
        return ClockifyRawRecord::query()->with($relations)->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ClockifyRawRecord
    {
        return ClockifyRawRecord::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ClockifyRawRecord $record, array $data): ClockifyRawRecord
    {
        $record->update($data);

        return $record->refresh();
    }

    public function delete(ClockifyRawRecord $record): bool
    {
        return (bool) $record->delete();
    }

    public function find(int $workspaceId, SyncEntityType $entityType, string $clockifyId): ?ClockifyRawRecord
    {
        return ClockifyRawRecord::query()
            ->withoutOrganizationScope()
            ->where('workspace_id', $workspaceId)
            ->where('entity_type', $entityType->value)
            ->where('clockify_id', $clockifyId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function upsert(array $attributes, array $values): ClockifyRawRecord
    {
        return ClockifyRawRecord::query()
            ->withoutOrganizationScope()
            ->updateOrCreate($attributes, $values);
    }

    /**
     * @param  array<int, string>  $clockifyIds
     * @return array<string, string>
     */
    public function existingHashes(int $workspaceId, SyncEntityType $entityType, array $clockifyIds): array
    {
        if ($clockifyIds === []) {
            return [];
        }

        $hashes = [];

        $records = ClockifyRawRecord::query()
            ->withoutOrganizationScope()
            ->where('workspace_id', $workspaceId)
            ->where('entity_type', $entityType->value)
            ->whereIn('clockify_id', $clockifyIds)
            ->get();

        foreach ($records as $record) {
            $hashes[(string) $record->clockify_id] = (string) $record->payload_hash;
        }

        return $hashes;
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
            if (isset($row['payload']) && is_array($row['payload'])) {
                $row['payload'] = json_encode($row['payload']);
            }

            return $row;
        }, $rows);

        return ClockifyRawRecord::query()->upsert(
            $prepared,
            ['organization_id', 'workspace_id', 'entity_type', 'clockify_id'],
            ['payload', 'payload_hash', 'source', 'fetched_at'],
        );
    }

    public function deleteForWorkspace(int $workspaceId, ?SyncEntityType $entityType = null): int
    {
        $query = ClockifyRawRecord::query()
            ->withoutOrganizationScope()
            ->where('workspace_id', $workspaceId);

        if ($entityType !== null) {
            $query->where('entity_type', $entityType->value);
        }

        return $query->delete();
    }

    public function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return ClockifyRawRecord::query()
            ->withoutOrganizationScope()
            ->where('fetched_at', '<', now()->subDays($days))
            ->delete();
    }
}
