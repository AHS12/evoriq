<?php

namespace App\Services\Sync;

use App\Enums\SyncEntityType;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Services\Pipeline\PipelineEventRecorder;

/**
 * Projects Clockify sync run/job lifecycle into the shared pipeline event
 * stream (SYNC-14) so a historical import appears in the same live timeline as
 * every other background run. Every method is a no-op when the job is missing
 * its run (defensive: a job is always created inside a run).
 */
class SyncEventEmitter
{
    public function __construct(
        protected PipelineEventRecorder $recorder,
    ) {}

    /**
     * The run was persisted and queued.
     */
    public function runQueued(ClockifySyncRun $run): void
    {
        $this->recorder->dispatched($run, [
            'mode' => $run->mode->value,
            'trigger' => $run->trigger->value,
            'jobs' => $run->total_jobs,
            'range_start' => $run->range_start?->toIso8601String(),
            'range_end' => $run->range_end?->toIso8601String(),
            'correlation_id' => $run->correlation_id,
        ]);
    }

    /**
     * The run began processing.
     */
    public function runStarted(ClockifySyncRun $run): void
    {
        $this->recorder->started($run);
    }

    /**
     * A job began; the job's entity is its stage.
     */
    public function jobStarted(ClockifySyncJob $job): void
    {
        $run = $job->syncRun;

        if ($run === null) {
            return;
        }

        $this->recorder->stageStarted(
            $run,
            $this->stageKey($job->entity_type),
            $this->jobContext($job),
            $job->attempt,
        );
    }

    /**
     * A page of a job was persisted.
     */
    public function jobProgress(ClockifySyncJob $job): void
    {
        $run = $job->syncRun;

        if ($run === null) {
            return;
        }

        $this->recorder->progress(
            $run,
            $job->records_processed,
            null,
            $this->stageKey($job->entity_type),
            $job->attempt,
        );
    }

    /**
     * A job reached a terminal success.
     */
    public function jobCompleted(ClockifySyncJob $job): void
    {
        $run = $job->syncRun;

        if ($run === null) {
            return;
        }

        $this->recorder->stageCompleted(
            $run,
            $this->stageKey($job->entity_type),
            $this->durationMs($job),
            $this->jobContext($job),
        );
    }

    /**
     * A job failed (recoverable at the run level).
     */
    public function jobFailed(ClockifySyncJob $job, ?string $message = null): void
    {
        $run = $job->syncRun;

        if ($run === null) {
            return;
        }

        $this->recorder->error(
            $run,
            $message ?? $job->last_error ?? __('The sync job failed.'),
            $this->jobContext($job),
            $this->stageKey($job->entity_type),
            $job->attempt,
        );
    }

    /**
     * A job failed and a retry has been scheduled (SYNC-13).
     */
    public function jobRetryScheduled(ClockifySyncJob $job): void
    {
        $run = $job->syncRun;

        if ($run === null) {
            return;
        }

        $this->recorder->retryScheduled(
            $run,
            __('Retrying :entity.', ['entity' => $job->entity_type->label()]),
            $job->attempt,
            $this->jobContext($job),
        );
    }

    /**
     * The run is waiting for the API budget window to reset.
     */
    public function budgetExhausted(ClockifySyncRun $run, int $resetsIn): void
    {
        $this->recorder->warning(
            $run,
            __('Clockify API limit reached — waiting :seconds seconds for the window to reset.', [
                'seconds' => $resetsIn,
            ]),
            context: ['resets_in' => $resetsIn],
        );
    }

    /**
     * The run finished successfully.
     */
    public function runCompleted(ClockifySyncRun $run): void
    {
        $this->recorder->completed($run, context: $this->runCounts($run));
    }

    /**
     * The run failed terminally.
     */
    public function runFailed(ClockifySyncRun $run, ?string $message = null): void
    {
        $this->recorder->failed($run, $message ?? $run->error_message, $this->runCounts($run));
    }

    /**
     * The run was cancelled.
     */
    public function runCancelled(ClockifySyncRun $run): void
    {
        $this->recorder->cancelled($run);
    }

    /**
     * The stable stage key for an entity (one stage per entity, aggregated
     * across the per-user/per-partition jobs that share it).
     */
    public function stageKey(SyncEntityType $entity): string
    {
        return strtolower($entity->value);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobContext(ClockifySyncJob $job): array
    {
        return array_filter([
            'entity_type' => $job->entity_type->value,
            'entity_label' => $job->entity_type->label(),
            'phase' => $job->phase->value,
            'job_id' => $job->id,
            'user_clockify_id' => $job->user_clockify_id,
            'range_start' => $job->range_start?->toIso8601String(),
            'range_end' => $job->range_end?->toIso8601String(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function runCounts(ClockifySyncRun $run): array
    {
        return [
            'jobs' => $run->total_jobs,
            'completed_jobs' => $run->completed_jobs,
            'created' => $run->records_created,
            'updated' => $run->records_updated,
            'deleted' => $run->records_deleted,
        ];
    }

    private function durationMs(ClockifySyncJob $job): ?int
    {
        if ($job->started_at === null || $job->completed_at === null) {
            return null;
        }

        return (int) round($job->started_at->diffInMilliseconds($job->completed_at));
    }
}
