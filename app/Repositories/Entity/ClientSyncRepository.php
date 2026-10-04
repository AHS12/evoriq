<?php

namespace App\Repositories\Entity;

use App\Models\ClockifyClient;
use App\Repositories\Sync\AbstractSyncUpsertRepository;

/**
 * Idempotent persistence for the client dimension (ENT-03), keyed by
 * `(organization_id, workspace_id, clockify_id)`.
 */
final class ClientSyncRepository extends AbstractSyncUpsertRepository
{
    protected function syncModel(): string
    {
        return ClockifyClient::class;
    }
}
