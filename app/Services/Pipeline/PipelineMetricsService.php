<?php

namespace App\Services\Pipeline;

use App\Enums\DataProcessingJobStatus;
use App\Enums\PipelineFailureReason;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates the operator-facing pipeline health snapshot (PIPE-10): status
 * counts, the 7-day success rate, duration percentiles, throughput, recent
 * failures, stale runs and the top failure reasons. Cached briefly so the
 * developer page and the health check stay cheap.
 *
 * All queries live in the repository; this service only shapes the numbers.
 */
class PipelineMetricsService
{
    public function __construct(
        protected DataProcessingJobRepositoryInterface $jobs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return Cache::remember(
            'pipeline.metrics',
            now()->addSeconds(max(1, (int) config('pipeline.metrics_cache_seconds', 15))),
            fn (): array => $this->build(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        $now = now();
        $since7 = $now->copy()->subDays(7);
        $since24h = $now->copy()->subHours(24);
        $staleCutoff = $now->copy()->subSeconds(max(1, (int) config('pipeline.stale_after', 1920)));

        $statuses = $this->jobs->statusCounts();
        $finished = $this->jobs->finishedRunsSince($since7);

        $completed = $finished->where('status', DataProcessingJobStatus::COMPLETED)->count();
        $failed = $finished->where('status', DataProcessingJobStatus::FAILED)->count();
        $total = $completed + $failed;

        $durations = $finished
            ->filter(static fn ($job): bool => $job->started_at !== null && $job->completed_at !== null)
            ->map(static fn ($job): int => (int) round($job->started_at->diffInSeconds($job->completed_at, true) * 1000))
            ->sort()
            ->values()
            ->all();

        $reasons = $finished
            ->where('status', DataProcessingJobStatus::FAILED)
            ->pluck('failure_reason')
            ->filter(static fn ($reason): bool => $reason instanceof PipelineFailureReason)
            ->countBy(static fn (PipelineFailureReason $reason): string => $reason->value)
            ->sortDesc()
            ->take(5);

        return [
            'active_now' => ($statuses['pending'] ?? 0) + ($statuses['processing'] ?? 0),
            'queued' => $statuses['pending'] ?? 0,
            'runs_today' => $this->jobs->runsCreatedSince($now->copy()->startOfDay()),
            'completed_7d' => $completed,
            'failed_7d' => $failed,
            'success_rate_7d' => $total > 0 ? round(($completed / $total) * 100, 1) : null,
            'avg_duration_ms' => $this->average($durations),
            'p95_duration_ms' => $this->percentile($durations, 95),
            'records_7d' => (int) $finished->sum('success_count'),
            'failures_24h' => $this->jobs->failedRecentCount(null, $since24h),
            'stale' => $this->jobs->staleProcessingBefore($staleCutoff)->count(),
            'failure_reasons' => $reasons
                ->map(fn (int $count, string $reason): array => [
                    'reason' => $reason,
                    'label' => PipelineFailureReason::from($reason)->label(),
                    'count' => $count,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, int>  $durations
     */
    private function average(array $durations): ?int
    {
        if ($durations === []) {
            return null;
        }

        return (int) round(array_sum($durations) / count($durations));
    }

    /**
     * Nearest-rank percentile over an ascending list (portable across drivers).
     *
     * @param  array<int, int>  $durations
     */
    private function percentile(array $durations, float $percentile): ?int
    {
        $count = count($durations);

        if ($count === 0) {
            return null;
        }

        $rank = (int) max(1, (int) ceil(($percentile / 100) * $count));

        return $durations[min($rank, $count) - 1];
    }
}
