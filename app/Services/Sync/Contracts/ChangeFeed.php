<?php

namespace App\Services\Sync\Contracts;

use App\DTOs\Sync\EntityChangePage;
use App\Enums\SyncEntityType;
use App\Models\ClockifyWorkspace;
use Carbon\CarbonInterface;

/**
 * A swappable source of "what changed upstream" (SYNC-06). The Clockify
 * implementation wraps the experimental Entity Changes API; callers (SYNC-07,
 * SYNC-10/11/16) never depend on the concrete feed.
 */
interface ChangeFeed
{
    /**
     * Fetch one page of changes for a single entity type over a range.
     */
    public function since(
        ClockifyWorkspace $workspace,
        CarbonInterface $from,
        CarbonInterface $to,
        SyncEntityType $type,
        int $page = 0,
    ): EntityChangePage;
}
