<?php

namespace App\Events\Sync;

use App\Models\ClockifySyncRun;

/**
 * Fired when a sync run reaches a terminal state (SYNC-09). Analytics recompute
 * (ANA-03) and run notifications (PIPE-11) subscribe to this rather than being
 * wired into the orchestrator.
 */
final class SyncRunFinished
{
    public function __construct(
        public readonly ClockifySyncRun $run,
    ) {}
}
