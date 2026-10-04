<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyWorkspace;
use App\Repositories\Sync\AbstractSyncUpsertRepository;

/**
 * Idempotent persistence for the workspace dimension (ENT-01). The workspace is
 * the parent scoped by every other entity, so it keys by
 * `(organization_id, clockify_id)` rather than the generic
 * `(organization_id, workspace_id, clockify_id)`.
 */
final class WorkspaceSyncRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return ClockifyWorkspace::class;
    }

    protected function syncUsesWorkspace(): bool
    {
        return false;
    }
}
