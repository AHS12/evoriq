<?php

namespace App\Services\Sync;

use App\Enums\RawRecordSource;
use App\Enums\SyncEntityType;
use App\Models\ClockifyRawRecord;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyRawRecordRepositoryInterface;

/**
 * Persists the last raw upstream payload for each entity (SYNC-05) so the
 * normalized model can be rebuilt if a mapping changes or a field is discovered
 * later. Writes are keyed by `(org, workspace, entity_type, clockify_id)` and
 * deduped by payload hash, so re-running a page is cheap.
 */
class RawRecordStore
{
    public function __construct(
        protected ClockifyRawRecordRepositoryInterface $records,
    ) {}

    /**
     * Persist a payload, skipping the write when it is unchanged. Returns the
     * stored record, or null when nothing changed.
     *
     * @param  array<string, mixed>  $payload
     */
    public function put(
        ClockifyWorkspace $workspace,
        SyncEntityType $entityType,
        string $clockifyId,
        array $payload,
        RawRecordSource $source = RawRecordSource::API,
    ): ?ClockifyRawRecord {
        $hash = $this->hash($payload);

        $existing = $this->records->find($workspace->id, $entityType, $clockifyId);

        if ($existing !== null && $existing->payload_hash === $hash) {
            return null;
        }

        return $this->records->upsert(
            [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'entity_type' => $entityType->value,
                'clockify_id' => $clockifyId,
            ],
            [
                'payload' => $payload,
                'payload_hash' => $hash,
                'source' => $source->value,
                'fetched_at' => now(),
            ],
        );
    }

    /**
     * Persist a fetched page in one batch, skipping unchanged rows. Returns the
     * number of rows written.
     *
     * @param  iterable<int, mixed>  $payloads
     */
    public function putMany(
        ClockifyWorkspace $workspace,
        SyncEntityType $entityType,
        iterable $payloads,
        RawRecordSource $source = RawRecordSource::API,
    ): int {
        $byId = [];

        foreach ($payloads as $payload) {
            if (! is_array($payload) || ! isset($payload['id'])) {
                continue;
            }

            $byId[(string) $payload['id']] = $payload;
        }

        if ($byId === []) {
            return 0;
        }

        $existing = $this->records->existingHashes($workspace->id, $entityType, array_keys($byId));

        $fetchedAt = now();
        $rows = [];

        foreach ($byId as $clockifyId => $payload) {
            $hash = $this->hash($payload);

            if (($existing[$clockifyId] ?? null) === $hash) {
                continue;
            }

            $rows[] = [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'entity_type' => $entityType->value,
                'clockify_id' => $clockifyId,
                'payload' => $payload,
                'payload_hash' => $hash,
                'source' => $source->value,
                'fetched_at' => $fetchedAt,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        return $this->records->upsertMany($rows);
    }

    public function get(
        ClockifyWorkspace $workspace,
        SyncEntityType $entityType,
        string $clockifyId,
    ): ?ClockifyRawRecord {
        return $this->records->find($workspace->id, $entityType, $clockifyId);
    }

    /**
     * Remove every raw record for a workspace (optionally one entity type).
     */
    public function deleteFor(
        ClockifyWorkspace $workspace,
        ?SyncEntityType $entityType = null,
    ): int {
        return $this->records->deleteForWorkspace($workspace->id, $entityType);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload));
    }
}
