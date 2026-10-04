<?php

namespace App\Console\Commands\Sync;

use App\Repositories\Contracts\ClockifySyncRunRepositoryInterface;
use App\Services\Sync\SyncRunService;
use Illuminate\Console\Command;

/**
 * Safety net for budget-parked / retry-scheduled sync runs (SYNC-13/SYNC-20).
 *
 * A run normally resumes itself through a delayed `ResumeSyncRunJob`, but that
 * depends on a worker being alive at the exact reset (and on the right queue).
 * This scheduled command re-drives any non-final run that still has dispatchable
 * work, so an import always continues once the API window resets — regardless
 * of worker restarts or lost delayed jobs.
 */
class ResumeSyncRunsCommand extends Command
{
    protected $signature = 'sync:resume
        {--limit=50 : Maximum runs to resume in one pass}';

    protected $description = 'Resume sync runs that have dispatchable (parked/retry) work';

    public function handle(ClockifySyncRunRepositoryInterface $runs, SyncRunService $service): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $resumable = $runs->resumable()->take($limit);

        if ($resumable->isEmpty()) {
            $this->info('No resumable sync runs found.');

            return self::SUCCESS;
        }

        foreach ($resumable as $run) {
            $service->resume($run);
        }

        $this->info("Resumed {$resumable->count()} sync run(s).");

        return self::SUCCESS;
    }
}
