<?php

namespace App\Services\Sync;

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use Carbon\CarbonInterface;

/**
 * The scope a single sync job runs in (SYNC-04): the connection, the workspace,
 * the date range and — for per-user fact entities — the Clockify user id.
 */
final readonly class SyncContext
{
    public function __construct(
        public ClockifyConnection $connection,
        public ClockifyWorkspace $workspace,
        public ?CarbonInterface $rangeStart = null,
        public ?CarbonInterface $rangeEnd = null,
        public int $pageSize = 200,
        public ?string $userId = null,
    ) {}
}
