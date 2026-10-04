<?php

namespace App\Services\Sync;

use App\DTOs\Sync\EntityChangeDTO;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyEntityChangeRepositoryInterface;

/**
 * Persists changes from the Entity Changes feed into `clockify_entity_changes`
 * (SYNC-06). Recording is idempotent: the same change event (same source time)
 * upserts rather than duplicating.
 */
class EntityChangeRecorder
{
    public function __construct(
        protected ClockifyEntityChangeRepositoryInterface $changes,
    ) {}

    /**
     * @param  array<int, EntityChangeDTO>  $changes
     * @return int Number of rows written.
     */
    public function record(ClockifyWorkspace $workspace, array $changes): int
    {
        if ($changes === []) {
            return 0;
        }

        $detectedAt = now();
        $rows = [];

        foreach ($changes as $change) {
            $rows[] = [
                'organization_id' => $workspace->organization_id,
                'workspace_id' => $workspace->id,
                'entity_type' => $change->entityType->value,
                'clockify_id' => $change->clockifyId,
                'change_type' => $change->changeType->value,
                'source_at' => $change->sourceAt ?? $detectedAt,
                'detected_at' => $detectedAt,
                'raw_data' => $change->raw,
            ];
        }

        return $this->changes->upsertMany($rows);
    }
}
