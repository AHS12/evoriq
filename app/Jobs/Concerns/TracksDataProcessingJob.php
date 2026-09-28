<?php

namespace App\Jobs\Concerns;

use App\Models\DataProcessingJob;
use App\Services\DataProcessingJob\DataProcessingJobService;
use App\Services\Pipeline\PipelineEventRecorder;
use Throwable;

/**
 * Marks the tracked {@see DataProcessingJob} row failed when the queued job
 * ultimately fails — after retries are exhausted, or on a timeout (with
 * `#[FailOnTimeout]`).
 *
 * Handling failure here (rather than in `handle()`'s catch) keeps the row in
 * `PROCESSING` across retries and only flips it to `FAILED` when the queue
 * truly gives up.
 *
 * @property DataProcessingJob $dataProcessingJob
 * @property int $tries
 */
trait TracksDataProcessingJob
{
    public function failed(?Throwable $e): void
    {
        $job = $this->dataProcessingJob->fresh();

        if (! $job instanceof DataProcessingJob || $job->status->isFinal()) {
            return;
        }

        $recorder = app(PipelineEventRecorder::class);
        $message = $e?->getMessage() ?? __('The job failed.');

        $recorder->error($job, $message);

        if ($this->job !== null && $this->attempts() < $this->tries) {
            $recorder->retryScheduled($job, $message, $this->attempts() + 1);

            return;
        }

        $service = app(DataProcessingJobService::class);

        $service->markFailed($job, $message);

        try {
            $service->notifyFinished($job);
        } catch (Throwable) {
            // Never let a notification failure mask the failure handling.
        }
    }
}
