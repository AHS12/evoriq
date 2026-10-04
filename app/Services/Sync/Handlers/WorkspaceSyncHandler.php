<?php

namespace App\Services\Sync\Handlers;

use App\Enums\SyncDeletePolicy;
use App\Enums\SyncEntityType;
use App\Enums\SyncPhase;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Repositories\Entity\WorkspaceSyncRepository;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\AbstractSyncHandler;
use App\Services\Sync\SyncContext;
use App\Support\Clockify\WorkspaceMapper;

/**
 * Ingests the workspace dimension (ENT-01) from `GET /workspaces/{id}`. This is
 * the root reference entity — it supplies the currency, time zone and default
 * rates every fact is scoped to, so it always loads first (SYNC-03).
 *
 * Workspaces retire rather than delete: an upstream deletion marks the
 * workspace inactive (ENT-13) instead of destroying any rows.
 */
class WorkspaceSyncHandler extends AbstractSyncHandler
{
    public function __construct(
        ClockifyClient $client,
        protected WorkspaceMapper $mapper,
        protected ClockifyWorkspaceRepositoryInterface $workspaces,
    ) {
        parent::__construct($client);
    }

    public function entityType(): SyncEntityType
    {
        return SyncEntityType::WORKSPACE;
    }

    /**
     * ENT-13 policy: mark inactive (there is no `deleted_at` on the workspace
     * dimension), so syncing stops without cascading into historical facts.
     */
    public function delete(SyncContext $context, string $clockifyId): void
    {
        $this->workspaces->markInactive($context->connection, $clockifyId);
    }

    public function phase(): SyncPhase
    {
        return SyncPhase::REFERENCE;
    }

    protected function repositoryClass(): string
    {
        return WorkspaceSyncRepository::class;
    }

    protected function endpoint(SyncContext $context): string
    {
        return "/workspaces/{$context->workspace->clockify_id}";
    }

    protected function mapRow(array $raw, SyncContext $context): array
    {
        // Drop absent fields so a partial detail response never erases values
        // already stored (Clockify omits optional fields rather than nulling).
        $attributes = array_filter(
            $this->mapper->map($raw),
            static fn (mixed $value): bool => $value !== null,
        );

        return [
            ...$attributes,
            // The dimension belongs to the connection it was discovered through;
            // the workspace row always exists by the time sync runs (CONN-03),
            // but stamping it keeps the create path valid too.
            'connection_id' => $context->workspace->connection_id,
            'raw_data' => $raw,
        ];
    }

    protected function deletePolicy(): SyncDeletePolicy
    {
        return SyncDeletePolicy::NONE;
    }
}
