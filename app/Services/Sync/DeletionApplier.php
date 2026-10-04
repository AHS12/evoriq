<?php

namespace App\Services\Sync;

use App\DTOs\Sync\EntityChangeDTO;
use App\Enums\EntityChangeType;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyDeletedEntityRepositoryInterface;

/**
 * Records upstream deletions and applies them to the normalized model (SYNC-07).
 * A record's absence from a page is never a deletion; only this feed is. The
 * per-entity policy (soft-delete dimensions/facts, remove join rows) lives in
 * each handler's `delete()`; the applier just dispatches and marks applied.
 */
class DeletionApplier
{
    public function __construct(
        protected ClockifyDeletedEntityRepositoryInterface $deletions,
        protected SyncHandlerRegistry $handlers,
    ) {}

    /**
     * Record the DELETED changes from the feed, idempotently. Re-ingesting a
     * deletion resets it to unapplied so a delete→restore→delete cycle is
     * re-applied.
     *
     * @param  array<int, EntityChangeDTO>  $changes
     * @return int Number of deletions recorded.
     */
    public function ingest(ClockifyWorkspace $workspace, array $changes): int
    {
        $count = 0;

        foreach ($changes as $change) {
            if ($change->changeType !== EntityChangeType::DELETED) {
                continue;
            }

            $this->deletions->upsert(
                [
                    'organization_id' => $workspace->organization_id,
                    'workspace_id' => $workspace->id,
                    'entity_type' => $change->entityType->value,
                    'clockify_id' => $change->clockifyId,
                ],
                [
                    'deleted_at' => $change->sourceAt ?? now(),
                    'raw_data' => $change->raw,
                    'applied_at' => null,
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * Apply every unapplied deletion for a workspace through its entity handler.
     * Deletions whose entity has no registered handler are left for later.
     *
     * @return int Number of deletions applied.
     */
    public function applyPending(ClockifyWorkspace $workspace, int $limit = 100): int
    {
        $pending = $this->deletions->unapplied($workspace->id, $limit);

        if ($pending->isEmpty()) {
            return 0;
        }

        $connection = $workspace->connection;

        if ($connection === null) {
            return 0;
        }

        $context = new SyncContext($connection, $workspace);

        $applied = 0;

        foreach ($pending as $deletion) {
            if (! $this->handlers->has($deletion->entity_type)) {
                continue;
            }

            $this->handlers
                ->resolve($deletion->entity_type)
                ->delete($context, $deletion->clockify_id);

            $this->deletions->markApplied($deletion);
            $applied++;
        }

        return $applied;
    }
}
