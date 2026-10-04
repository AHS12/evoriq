<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyCustomField;
use App\Repositories\Sync\AbstractSyncUpsertRepository;

/**
 * Idempotent persistence for custom-field definitions (ENT-09), keyed by
 * `(organization_id, workspace_id, clockify_id)`.
 */
final class CustomFieldSyncRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return ClockifyCustomField::class;
    }
}
