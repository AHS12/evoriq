<?php

namespace App\Jobs;

use App\Enums\QueueName;
use App\Registry\QueueRegistry;
use App\Services\DataProcessingJob\DataProcessingJobService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Bulk-retries a large set of finished runs on the `default` channel (PIPE-07)
 * so a mass re-dispatch cannot flood the originating request. Small sets are
 * retried inline by {@see DataProcessingJobService::retryFailed()}.
 */
class RetryFailedDataProcessingJobs implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * @param  array<int, int>  $ids
     */
    public function __construct(public array $ids)
    {
        $config = QueueRegistry::get(QueueName::DEFAULT);

        $this->tries = $config->tries;
        $this->timeout = $config->timeout;

        $this->onQueue($config->name);
    }

    public function handle(DataProcessingJobService $service): void
    {
        foreach ($this->ids as $id) {
            $job = $service->find((int) $id);

            if ($job === null || ! $job->status->isFinal()) {
                continue;
            }

            try {
                $service->retry($job);
            } catch (RuntimeException) {
                // The row changed since the set was computed; skip it.
            }
        }
    }
}
