<?php

namespace App\Console\Commands\Sync;

use App\Jobs\Sync\SyncEntityJob;
use App\Repositories\Contracts\ClockifySyncJobRepositoryInterface;
use App\Services\Sync\SyncEventEmitter;
use App\Services\Sync\SyncRetryPolicy;
use App\Services\Sync\SyncRunService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Reaps sync jobs whose worker was lost (crash, OOM, timeout) so no job is left
 * "running" forever (SYNC-13). A stale job is rescheduled (or failed once its
 * attempts are exhausted) and its run is resumed/finalized.
 */
class ReapStaleSyncJobsCommand extends Command
{
    protected $signature = 'sync:reap-stale
        {--limit=100 : Maximum jobs to reap in one pass}';

    protected $description = 'Fail/reschedule sync jobs whose worker was lost';

    public function handle(
        ClockifySyncJobRepositoryInterface $jobs,
        SyncRetryPolicy $policy,
        SyncEventEmitter $events,
    ): int {
        $staleAfter = max(1, (int) config('clockify.sync_job.stale_after', 900));
        $limit = max(1, (int) $this->option('limit'));

        $stale = $jobs->staleRunning(now()->subSeconds($staleAfter), $limit);

        if ($stale->isEmpty()) {
            $this->info('No stale sync jobs found.');

            return self::SUCCESS;
        }

        $requeued = 0;
        $failed = 0;

        foreach ($stale as $job) {
            $retried = $policy->retry($job, new RuntimeException(
                __('The worker running this sync job stopped unexpectedly.'),
            ));

            $job = $job->fresh();

            if ($job === null) {
                continue;
            }

            if ($retried) {
                $events->jobRetryScheduled($job);
                SyncEntityJob::dispatch($job->id, $job->syncRun?->priority->value)->delay($job->next_retry_at);
                $requeued++;
            } else {
                $events->jobFailed($job, $job->last_error);
                app(SyncRunService::class)->onJobFinished($job);
                $failed++;
            }
        }

        $this->info("Reaped sync jobs: {$requeued} requeued, {$failed} failed.");

        return self::SUCCESS;
    }
}
