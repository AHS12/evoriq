<?php

namespace App\Events\Sync;

use App\DTOs\Sync\ApiUsageSnapshot;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;

/**
 * Fired when a Clockify request cannot be reserved because the current budget
 * window is spent (SYNC-02). SYNC-14 maps this to a PIPE `WARNING` /
 * `RETRY_SCHEDULED` event; here it stays a plain domain event so the Clockify
 * client never depends on the pipeline UI.
 */
final class ApiBudgetExhausted
{
    public function __construct(
        public readonly ClockifyConnection $connection,
        public readonly ?ClockifyWorkspace $workspace,
        public readonly ApiUsageSnapshot $snapshot,
    ) {}
}
