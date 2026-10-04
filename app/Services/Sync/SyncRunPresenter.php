<?php

namespace App\Services\Sync;

use App\DTOs\Pipeline\PipelineEventWindowDTO;
use App\Enums\PipelineRunType;
use App\Enums\SyncJobStatus;
use App\Enums\SyncRunStatus;
use App\Models\ClockifySyncJob;
use App\Models\ClockifySyncRun;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Projects a {@see ClockifySyncRun} into the same run contract the pipeline UI
 * renders for a data processing run (SYNC-14, PIPE-02). Sync runs have no
 * `total_items`, so progress is measured across the run's jobs
 * (`completed_jobs / total_jobs`) and stages are derived from the jobs (one
 * stage per entity), while the event timeline comes from `pipeline_events`.
 */
class SyncRunPresenter
{
    public function __construct(
        protected PipelineEventRepositoryInterface $events,
    ) {}

    /**
     * The run contract consumed by the reused PIPE-05 components.
     *
     * @return array<string, mixed>
     */
    public function present(ClockifySyncRun $run): array
    {
        $run->loadMissing('jobs');

        /** @var Collection<int, ClockifySyncJob> $jobs */
        $jobs = $run->jobs;
        $stages = $this->stages($jobs);
        $progress = $this->progress($run, $jobs);
        $status = $this->status($run->status);
        $failure = $this->failure($run);
        $active = $run->status->isActive();

        $abilities = [
            'cancel' => $active,
            'retry' => ! $active && $run->status === SyncRunStatus::FAILED,
            'resume' => ! $active && $run->status !== SyncRunStatus::COMPLETED,
            'duplicate' => false,
            'duplicate_soon' => false,
            'download' => false,
            'delete' => false,
        ];

        return [
            'id' => $run->id,
            'run_type' => PipelineRunType::SYNC->value,
            'type' => 'import',
            'type_label' => $run->mode->label(),
            'type_icon' => 'download',
            'name' => $this->name($run),
            'entity' => [
                'value' => 'clockify',
                'label' => __('Clockify'),
                'icon' => 'refresh-cw',
            ],
            'entity_type' => 'clockify',
            'entity_label' => __('Clockify'),
            'entity_icon' => 'refresh-cw',
            'status' => $status,
            'status_label' => $run->status->label(),
            'stage' => $this->currentStage($stages),
            'progress' => $progress,
            'counts' => [
                'created' => $run->records_created,
                'updated' => $run->records_updated,
                'failed' => $this->failedJobs($jobs),
                'skipped' => 0,
            ],
            'stages' => $stages,
            'attempts' => [
                'current' => 1,
                'label' => __('Attempt :current', ['current' => 1]),
                'max' => 1,
                'next_retry_at' => null,
            ],
            'failure' => $failure,
            'timing' => $this->timing($run, $jobs),
            'abilities' => $abilities,
            'timeline' => [],
            'issues' => [],
            'advanced' => null,
            'job_id' => (string) $run->id,
            'format' => null,
            'format_label' => null,
            'filters' => null,
            'parameters' => [
                'mode' => $run->mode->value,
                'trigger' => $run->trigger->value,
                'range_start' => $run->range_start?->toIso8601String(),
                'range_end' => $run->range_end?->toIso8601String(),
                'jobs' => $run->total_jobs,
            ],
            'file_name' => null,
            'file_size' => null,
            'input_file_name' => null,
            'total_items' => $progress['total'],
            'processed_items' => $progress['processed'],
            'success_count' => $run->records_created,
            'error_count' => $this->failedJobs($jobs),
            'error_message' => $run->error_message,
            'errors' => [],
            'progress_percentage' => $progress['percentage'],
            'artifacts' => [],
            'download_url' => null,
            'downloadable' => false,
            'owner' => null,
            'duration' => null,
            'can' => $abilities,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }

    /**
     * A bounded window of a sync run's event stream (PIPE-05).
     *
     * - neither param → newest tail (oldest-first);
     * - `after` → only newer events (append);
     * - `before` → the previous page of older events (prepend).
     */
    public function eventWindow(ClockifySyncRun $run, ?int $after = null, ?int $before = null, ?int $limit = null): PipelineEventWindowDTO
    {
        $limit = $limit ?? max(1, (int) config('pipeline.timeline_page_size', 100));
        $type = PipelineRunType::SYNC;
        $runId = $run->pipelineRunId();

        if ($after !== null) {
            $events = $this->events->afterSequence($type, $runId, $after, $limit);
            $hasMoreOlder = false;
        } elseif ($before !== null) {
            $events = $this->events->beforeSequence($type, $runId, $before, $limit);
            $hasMoreOlder = $events->isNotEmpty()
                && $this->events->existsBefore($type, $runId, (int) $events->first()->sequence);
        } else {
            $events = $this->events->tailForRun($type, $runId, $limit);
            $hasMoreOlder = $events->isNotEmpty()
                && $this->events->existsBefore($type, $runId, (int) $events->first()->sequence);
        }

        $events = $events->values();

        return new PipelineEventWindowDTO(
            events: $events,
            oldestSequence: $events->isNotEmpty() ? (int) $events->first()->sequence : null,
            latestSequence: $events->isNotEmpty() ? (int) $events->last()->sequence : null,
            hasMoreOlder: $hasMoreOlder,
        );
    }

    private function name(ClockifySyncRun $run): string
    {
        return __('Historical import');
    }

    /**
     * @param  Collection<int, ClockifySyncJob>  $jobs
     * @return array<int, array<string, mixed>>
     */
    private function stages(Collection $jobs): array
    {
        $grouped = [];

        foreach ($jobs as $job) {
            $key = strtolower($job->entity_type->value);
            $grouped[$key] ??= [
                'entity' => $job->entity_type,
                'jobs' => collect(),
            ];
            $grouped[$key]['jobs']->push($job);
        }

        $stages = [];

        foreach ($grouped as $key => $group) {
            /** @var Collection<int, ClockifySyncJob> $entityJobs */
            $entityJobs = $group['jobs'];
            $started = $entityJobs->pluck('started_at')->filter()->min();
            $ended = $entityJobs->pluck('completed_at')->filter()->max();

            $stages[] = [
                'key' => $key,
                'label' => $group['entity']->label(),
                'status' => $this->stageStatus($entityJobs),
                'started_at' => $started?->toIso8601String(),
                'ended_at' => $ended?->toIso8601String(),
                'duration_ms' => $this->stageDuration($started, $ended),
                'processed' => (int) $entityJobs->sum('records_processed'),
                'total' => null,
            ];
        }

        return $stages;
    }

    /**
     * @param  Collection<int, ClockifySyncJob>  $jobs
     */
    private function stageStatus(Collection $jobs): string
    {
        if ($jobs->contains(fn (ClockifySyncJob $job): bool => $job->status === SyncJobStatus::RUNNING)) {
            return 'running';
        }

        if ($jobs->contains(fn (ClockifySyncJob $job): bool => $job->status === SyncJobStatus::FAILED)) {
            return 'failed';
        }

        if ($jobs->contains(fn (ClockifySyncJob $job): bool => $job->status === SyncJobStatus::CANCELLED)) {
            return 'cancelled';
        }

        if ($jobs->every(fn (ClockifySyncJob $job): bool => $job->status === SyncJobStatus::COMPLETED)) {
            return 'completed';
        }

        return 'pending';
    }

    private function stageDuration(?CarbonInterface $started, ?CarbonInterface $ended): ?int
    {
        if ($started === null) {
            return null;
        }

        $end = $ended ?? now();

        return (int) round($started->diffInMilliseconds($end));
    }

    /**
     * @param  Collection<int, ClockifySyncJob>  $jobs
     * @return array<string, mixed>
     */
    private function progress(ClockifySyncRun $run, Collection $jobs): array
    {
        $total = $run->total_jobs;
        $processed = min($run->completed_jobs, $total);
        $end = $run->completed_at ?? now();
        $elapsed = $run->started_at !== null
            ? max(0, (int) $run->started_at->diffInSeconds($end))
            : 0;

        return [
            'total' => $total,
            'processed' => $processed,
            'percentage' => $total > 0 ? (int) round($processed / $total * 100) : 0,
            'indeterminate' => $total === 0,
            'elapsed_seconds' => $elapsed,
            'eta_seconds' => null,
            'throughput_per_min' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $stages
     */
    private function currentStage(array $stages): ?string
    {
        foreach (array_reverse($stages) as $stage) {
            if ($stage['status'] === 'running') {
                return $stage['label'];
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, ClockifySyncJob>  $jobs
     * @return array<string, mixed>
     */
    private function timing(ClockifySyncRun $run, Collection $jobs): array
    {
        $heartbeat = $jobs->pluck('heartbeat_at')->filter()->max();
        $active = $run->status->isActive();
        $staleAfter = (int) config('pipeline.stale_after', 1920);
        $reference = $heartbeat ?? $run->started_at;
        $stale = $active
            && $reference !== null
            && $reference->diffInSeconds(now()) > $staleAfter;

        $durationMs = null;

        if ($run->started_at !== null && $run->completed_at !== null) {
            $durationMs = (int) round($run->started_at->diffInMilliseconds($run->completed_at));
        }

        return [
            'dispatched_at' => $run->created_at?->toIso8601String(),
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'duration_ms' => $durationMs,
            'last_heartbeat_at' => $heartbeat?->toIso8601String(),
            'stale' => $stale,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function failure(ClockifySyncRun $run): ?array
    {
        if ($run->status === SyncRunStatus::FAILED) {
            return [
                'reason' => 'sync_failed',
                'label' => __('Import failed'),
                'hint' => $run->error_message ?? __('The import could not be completed.'),
                'action' => 'retry',
                'message' => $run->error_message,
            ];
        }

        if ($run->status === SyncRunStatus::CANCELLED) {
            return [
                'reason' => 'cancelled',
                'label' => __('Import cancelled'),
                'hint' => __('The import was cancelled.'),
                'action' => null,
                'message' => null,
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, ClockifySyncJob>  $jobs
     */
    private function failedJobs(Collection $jobs): int
    {
        return $jobs->filter(fn (ClockifySyncJob $job): bool => $job->status === SyncJobStatus::FAILED)->count();
    }

    private function status(SyncRunStatus $status): string
    {
        return match ($status) {
            SyncRunStatus::PENDING => 'pending',
            SyncRunStatus::RUNNING, SyncRunStatus::PAUSED => 'processing',
            SyncRunStatus::COMPLETED => 'completed',
            SyncRunStatus::FAILED => 'failed',
            SyncRunStatus::CANCELLED => 'cancelled',
        };
    }
}
