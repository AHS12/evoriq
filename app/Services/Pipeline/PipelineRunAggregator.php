<?php

namespace App\Services\Pipeline;

use App\DTOs\Pipeline\PipelineProgressDTO;
use App\DTOs\Pipeline\PipelineRunDTO;
use App\DTOs\Pipeline\PipelineStageDTO;
use App\DTOs\Pipeline\PipelineTimingDTO;
use App\Enums\DataProcessingJobStatus;
use App\Enums\PipelineEventType;
use App\Enums\PipelineFailureReason;
use App\Models\DataProcessingJob;
use App\Models\PipelineEvent;
use App\Repositories\Contracts\PipelineEventRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Folds a run's pipeline event stream plus its row into the single run contract
 * the frontend renders (PIPE-02): progress, ETA, throughput, stages, timing,
 * staleness and a typed failure reason.
 *
 * All reads go through the repository; this service never queries directly.
 */
class PipelineRunAggregator
{
    public function __construct(
        protected PipelineEventRepositoryInterface $events,
        protected FailureReasonResolver $failures,
    ) {}

    public function aggregate(DataProcessingJob $job, bool $withTimeline = false): PipelineRunDTO
    {
        $events = $this->events->allForRun($job->pipelineRunType(), $job->pipelineRunId());
        $stages = $this->deriveStages($job, $events);

        return new PipelineRunDTO(
            job: $job,
            progress: $this->buildProgress($job, $events, $stages),
            timing: $this->buildTiming($job, $events),
            stages: $stages,
            timeline: $withTimeline ? $this->timeline($events) : $events->take(0)->values(),
            failureReason: $this->resolveFailureReason($job),
            skipped: $this->countSkipped($job),
        );
    }

    /**
     * Fold STAGE_STARTED / STAGE_COMPLETED / PROGRESS events into stages.
     *
     * @param  Collection<int, PipelineEvent>  $events
     * @return array<int, PipelineStageDTO>
     */
    private function deriveStages(DataProcessingJob $job, Collection $events): array
    {
        /** @var array<string, CarbonInterface|null> $started */
        $started = [];
        /** @var array<string, CarbonInterface|null> $ended */
        $ended = [];
        /** @var array<string, int|null> $durations */
        $durations = [];
        /** @var array<string, int|null> $processed */
        $processed = [];
        /** @var array<string, int|null> $totals */
        $totals = [];
        /** @var list<string> $order */
        $order = [];

        $ensure = function (string $key) use (&$started, &$ended, &$durations, &$processed, &$totals, &$order): void {
            if (! array_key_exists($key, $started)) {
                $started[$key] = null;
                $ended[$key] = null;
                $durations[$key] = null;
                $processed[$key] = null;
                $totals[$key] = null;
                $order[] = $key;
            }
        };

        foreach ($events as $event) {
            $key = $event->stage;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $ensure($key);

            if ($event->type === PipelineEventType::STAGE_STARTED) {
                $started[$key] = $event->occurred_at;
                $ended[$key] = null;
            } elseif ($event->type === PipelineEventType::STAGE_COMPLETED) {
                $ended[$key] = $event->occurred_at;
                $durations[$key] = $event->duration_ms;
            } elseif ($event->type === PipelineEventType::PROGRESS) {
                $progress = $event->progress;

                if (is_array($progress)) {
                    if (isset($progress['processed']) && is_numeric($progress['processed'])) {
                        $processed[$key] = (int) $progress['processed'];
                    }

                    if (isset($progress['total']) && is_numeric($progress['total'])) {
                        $totals[$key] = (int) $progress['total'];
                    }
                }
            }
        }

        $lastKey = $order === [] ? null : $order[count($order) - 1];
        $stages = [];

        foreach ($order as $key) {
            $isLast = $key === $lastKey;

            $stages[] = new PipelineStageDTO(
                key: $key,
                label: Str::headline($key),
                status: $this->deriveStageStatus($job, $ended[$key] !== null, $isLast),
                startedAt: $started[$key]?->toIso8601String(),
                endedAt: $ended[$key]?->toIso8601String(),
                durationMs: $this->stageDuration($started[$key], $ended[$key], $durations[$key], $isLast),
                processed: $processed[$key],
                total: $totals[$key],
            );
        }

        return $stages;
    }

    /**
     * @param  Collection<int, PipelineEvent>  $events
     * @param  array<int, PipelineStageDTO>  $stages
     */
    private function buildProgress(DataProcessingJob $job, Collection $events, array $stages): PipelineProgressDTO
    {
        $total = $job->total_items;
        $processed = $job->processed_items;
        $processedCount = (int) $job->processed_items;

        $end = $job->completed_at ?? now();
        $elapsedSeconds = $job->started_at !== null
            ? max(0, (int) $job->started_at->diffInSeconds($end))
            : 0;

        $throughput = null;

        if ($elapsedSeconds > 0 && $processedCount > 0) {
            $throughput = (int) round($processedCount / ($elapsedSeconds / 60));
        }

        return new PipelineProgressDTO(
            total: $total,
            processed: $processed,
            percentage: $job->progressPercentage(),
            indeterminate: $total === null || $total === 0,
            elapsedSeconds: $elapsedSeconds,
            etaSeconds: $this->estimateEta($job, $events, $stages),
            throughputPerMin: $throughput,
        );
    }

    /**
     * Estimate the remaining seconds from the current stage's throughput.
     *
     * @param  Collection<int, PipelineEvent>  $events
     * @param  array<int, PipelineStageDTO>  $stages
     */
    private function estimateEta(DataProcessingJob $job, Collection $events, array $stages): ?int
    {
        if ($job->status !== DataProcessingJobStatus::PROCESSING) {
            return null;
        }

        $total = $job->total_items;

        if ($total === null || $total <= 0) {
            return null;
        }

        $currentStageKey = null;

        foreach (array_reverse($stages) as $stage) {
            if ($stage->status === 'running') {
                $currentStageKey = $stage->key;

                break;
            }
        }

        $points = $events
            ->filter(fn (PipelineEvent $event): bool => $event->type === PipelineEventType::PROGRESS
                && ($currentStageKey === null || $event->stage === $currentStageKey))
            ->filter(function (PipelineEvent $event): bool {
                $progress = $event->progress;

                return is_array($progress) && isset($progress['processed']) && is_numeric($progress['processed']);
            })
            ->values();

        if ($points->count() < max(1, (int) config('pipeline.eta_min_samples', 2))) {
            return null;
        }

        $first = $points->first();
        $last = $points->last();

        if (! $first instanceof PipelineEvent || ! $last instanceof PipelineEvent) {
            return null;
        }

        $firstProgress = $first->progress;
        $lastProgress = $last->progress;

        if (! is_array($firstProgress) || ! is_array($lastProgress)) {
            return null;
        }

        $deltaProcessed = (int) ($lastProgress['processed'] ?? 0) - (int) ($firstProgress['processed'] ?? 0);
        $deltaSeconds = $first->occurred_at->diffInSeconds($last->occurred_at);

        if ($deltaProcessed <= 0 || $deltaSeconds <= 0) {
            return null;
        }

        $rate = $deltaProcessed / $deltaSeconds;
        $remaining = $total - (int) ($lastProgress['processed'] ?? 0);

        return $remaining <= 0 ? 0 : (int) round($remaining / $rate);
    }

    /**
     * @param  Collection<int, PipelineEvent>  $events
     */
    private function buildTiming(DataProcessingJob $job, Collection $events): PipelineTimingDTO
    {
        $dispatchedAt = $job->created_at;

        foreach ($events as $event) {
            if ($event->type === PipelineEventType::DISPATCHED) {
                $dispatchedAt = $event->occurred_at;

                break;
            }
        }

        $durationMs = null;

        if ($job->started_at !== null && $job->completed_at !== null) {
            $durationMs = (int) round($job->started_at->diffInMilliseconds($job->completed_at));
        }

        return new PipelineTimingDTO(
            dispatchedAt: $dispatchedAt?->toIso8601String(),
            startedAt: $job->started_at?->toIso8601String(),
            completedAt: $job->completed_at?->toIso8601String(),
            durationMs: $durationMs,
            lastHeartbeatAt: $job->last_heartbeat_at?->toIso8601String(),
            stale: $job->isStale(),
        );
    }

    private function resolveFailureReason(DataProcessingJob $job): ?PipelineFailureReason
    {
        if ($job->status === DataProcessingJobStatus::CANCELLED) {
            return $job->failure_reason ?? PipelineFailureReason::CANCELLED;
        }

        if ($job->status !== DataProcessingJobStatus::FAILED) {
            return null;
        }

        return $job->failure_reason ?? $this->failures->fromMessage($job->error_message);
    }

    /**
     * @param  Collection<int, PipelineEvent>  $events
     * @return Collection<int, PipelineEvent>
     */
    private function timeline(Collection $events): Collection
    {
        return $events->take(-max(1, (int) config('pipeline.timeline_limit', 50)))->values();
    }

    private function countSkipped(DataProcessingJob $job): int
    {
        $errors = $job->errors;

        if (! is_array($errors)) {
            return 0;
        }

        return count(array_filter(
            $errors,
            static fn (mixed $error): bool => is_array($error) && ($error['type'] ?? null) === 'duplicate',
        ));
    }

    private function deriveStageStatus(DataProcessingJob $job, bool $completed, bool $isLast): string
    {
        if ($completed || ! $isLast) {
            return 'completed';
        }

        return match ($job->status) {
            DataProcessingJobStatus::FAILED => 'failed',
            DataProcessingJobStatus::CANCELLED => 'cancelled',
            DataProcessingJobStatus::COMPLETED => 'completed',
            default => 'running',
        };
    }

    private function stageDuration(?CarbonInterface $startedAt, ?CarbonInterface $endedAt, ?int $recorded, bool $isLast): ?int
    {
        if ($recorded !== null) {
            return $recorded;
        }

        if ($startedAt === null) {
            return null;
        }

        $end = $endedAt ?? ($isLast ? now() : null);

        if ($end === null) {
            return null;
        }

        return (int) round($startedAt->diffInMilliseconds($end));
    }
}
