<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyTag;
use App\Repositories\Sync\AbstractSyncUpsertRepository;

/**
 * Idempotent persistence for the tag dimension (ENT-06), keyed by
 * `(organization_id, workspace_id, clockify_id)`.
 */
final class TagSyncRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return ClockifyTag::class;
    }
}
