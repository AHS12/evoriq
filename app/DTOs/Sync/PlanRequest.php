<?php

namespace App\DTOs\Sync;

use App\Enums\SyncEntityType;
use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use Carbon\CarbonInterface;

/**
 * The input to the sync planner (SYNC-03): a workspace, a date frame and the
 * entity set to plan for.
 */
final readonly class PlanRequest
{
    /**
     * @param  array<int, SyncEntityType>|null  $entities  Null = the MVP set for the mode.
     */
    public function __construct(
        public ClockifyConnection $connection,
        public ClockifyWorkspace $workspace,
        public CarbonInterface $rangeStart,
        public CarbonInterface $rangeEnd,
        public SyncMode $mode = SyncMode::INITIAL,
        public SyncPriority $priority = SyncPriority::NORMAL,
        public ?array $entities = null,
        public int $pageSize = 200,
    ) {}
}
