<?php

namespace App\Repositories\Sync;

use App\Repositories\Concerns\UpsertsByClockifyId;
use App\Repositories\Contracts\SyncUpsertRepositoryInterface;

/**
 * Base class for every synced-entity repository (SYNC-08). A concrete
 * repository extends it and declares the Eloquent model it upserts:
 *
 *     final class ClientRepository extends AbstractSyncUpsertRepository
 *     {
 *         protected function syncModel(): string
 *         {
 *             return ClockifyClient::class;
 *         }
 *     }
 */
abstract class AbstractSyncUpsertRepository implements SyncUpsertRepositoryInterface
{
    use UpsertsByClockifyId;
}
