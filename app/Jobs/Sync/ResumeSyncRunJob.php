<?php

namespace App\Jobs\Sync;

use App\Enums\QueueName;
use App\Models\ClockifySyncRun;
use App\Registry\QueueRegistry;
use App\Services\Sync\SyncRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Resumes a sync run after the budget window resets (SYNC-09/SYNC-20). Scheduled
 * with a delay equal to the window's remaining seconds so a Free-plan run
 * pauses at the edge and continues without user action.
 */
class ResumeSyncRunJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    public function __construct(public int $syncRunId, public int $delaySeconds = 0)
    {
        $config = QueueRegistry::get(QueueName::DEFAULT);

        $this->timeout = $config->timeout;

        $this->onQueue($config->name);

        if ($delaySeconds > 0) {
            $this->delay(now()->addSeconds($delaySeconds));
        }
    }

    public function handle(SyncRunService $service): void
    {
        $run = ClockifySyncRun::query()->find($this->syncRunId);

        if ($run === null || $run->status->isFinal()) {
            return;
        }

        $service->dispatchPending($run);
    }
}
