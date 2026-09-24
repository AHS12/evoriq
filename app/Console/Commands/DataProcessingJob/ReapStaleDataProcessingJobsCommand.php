<?php

namespace App\Console\Commands\DataProcessingJob;

use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Fails "running" jobs whose worker was lost (crash, OOM, SIGKILL, timeout)
 * so the Data Processing Center never shows a job stuck in Processing forever.
 */
class ReapStaleDataProcessingJobsCommand extends Command
{
    protected $signature = 'data-processing:reap-stale
                            {--seconds= : Seconds after starting before a job is considered stale (defaults to config)}
                            {--dry-run : List stale jobs without updating them}';

    protected $description = 'Mark stalled data processing jobs (lost worker or timeout) as failed';

    public function handle(DataProcessingJobService $service): int
    {
        $seconds = $this->option('seconds') !== null
            ? (int) $this->option('seconds')
            : (int) config('exports.stale_after', 1920);

        $cutoff = now()->subSeconds($seconds);
        $jobs = $service->staleProcessingBefore($cutoff);

        if ($jobs->isEmpty()) {
            $this->info('No stale jobs found.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Job ID', 'Type', 'Started At'],
                $jobs->map(fn ($job): array => [
                    $job->job_id,
                    $job->type->value,
                    $job->started_at?->toDateTimeString() ?? '-',
                ])->all(),
            );

            return self::SUCCESS;
        }

        foreach ($jobs as $job) {
            $service->markFailed($job, 'The job stalled — its worker was lost or it timed out.');

            try {
                $service->notifyFinished($job);
            } catch (Throwable) {
                // A notification failure must not stop the sweep.
            }
        }

        $this->info("Reaped {$jobs->count()} stale job(s).");

        return self::SUCCESS;
    }
}
