<?php

namespace App\Jobs\Sync;

use App\Enums\QueueName;
use App\Enums\SyncPriority;
use App\Models\ClockifySyncJob;
use App\Registry\QueueRegistry;
use App\Services\Sync\SyncEventEmitter;
use App\Services\Sync\SyncJobRunner;
use App\Services\Sync\SyncRetryPolicy;
use App\Services\Sync\SyncRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\FailOnTimeout;
use Throwable;

/**
 * Thin queued wrapper around {@see SyncJobRunner} (SYNC-04). Runs on the queue
 * channel mapped from the run's priority (SYNC-20) so manual work drains before
 * scheduled/reconciliation work. Retries are owned by SYNC-13, so the queue
 * attempt count is always 1; the timeout is the heavy channel's.
 */
#[FailOnTimeout]
class SyncEntityJob implements ShouldQueue
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

    public function __construct(public int $syncJobId, public ?string $priority = null)
    {
        $priorityEnum = ($this->priority !== null ? SyncPriority::tryFrom($this->priority) : null)
            ?? SyncPriority::NORMAL;

        // Sync retries are managed by SyncRetryPolicy (SYNC-13), so the queue
        // must hand the failure straight to `failed()` after one attempt.
        $this->tries = 1;
        $this->timeout = QueueRegistry::get(QueueName::HEAVY)->timeout;

        $this->onQueue($priorityEnum->queue()->value);
    }

    public function handle(SyncJobRunner $runner, SyncRunService $service): void
    {
        $job = ClockifySyncJob::query()->find($this->syncJobId);

        if ($job === null || $job->status->isFinal()) {
            return;
        }

        $runner->run($job);

        $service->onJobFinished($job->fresh());
    }

    /**
     * Apply the retry/terminal policy when the queue gives up (or on timeout),
     * then let the run aggregate/finalize.
     */
    public function failed(?Throwable $e): void
    {
        $job = ClockifySyncJob::query()->find($this->syncJobId);

        if ($job === null || $job->status->isFinal()) {
            return;
        }

        $retried = app(SyncRetryPolicy::class)->retry($job, $e);
        $job = $job->fresh();

        if ($job === null) {
            return;
        }

        if ($retried) {
            app(SyncEventEmitter::class)->jobRetryScheduled($job);
            static::dispatch($job->id, $job->syncRun?->priority->value)->delay($job->next_retry_at);

            return;
        }

        app(SyncEventEmitter::class)->jobFailed($job, $e?->getMessage());

        app(SyncRunService::class)->onJobFinished($job);
    }
}
