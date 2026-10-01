<?php

namespace App\Services\Pipeline;

use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * The shared, cheap snapshot of the pipeline used by the global indicator
 * (PIPE-08): status counts, the worst tone, and the top active runs.
 *
 * The aggregate is cached for `pipeline.status_cache_seconds` so every page can
 * render it; it is invalidated from the Data Processing lifecycle writes.
 */
class PipelineStatusService
{
    public function __construct(
        protected DataProcessingJobRepositoryInterface $repository,
        protected PipelineRunAggregator $runs,
    ) {}

    /**
     * The scoped status summary. A null user id with `viewAll = false` yields an
     * empty summary (the caller has no pipeline access).
     *
     * @return array<string, mixed>
     */
    public function summary(?int $userId, bool $viewAll): array
    {
        if (! $viewAll && $userId === null) {
            return self::empty();
        }

        $seconds = max(0, (int) config('pipeline.status_cache_seconds', 5));

        if ($seconds === 0) {
            return $this->compute($userId, $viewAll);
        }

        return Cache::remember(
            $this->cacheKey($userId, $viewAll),
            $seconds,
            fn (): array => $this->compute($userId, $viewAll),
        );
    }

    /**
     * Forget the cached summaries affected by a run change. A null user id
     * clears the global scope and (when known) is expected to be paired with the
     * owner's id by the caller.
     */
    public static function forget(?int $userId): void
    {
        Cache::forget(self::keyFor(null, true));

        if ($userId !== null) {
            Cache::forget(self::keyFor($userId, false));
        }
    }

    /**
     * A neutral summary for users without pipeline access.
     *
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return [
            'active_count' => 0,
            'queued_count' => 0,
            'processing_count' => 0,
            'failed_recent' => 0,
            'worst_status' => 'success',
            'runs' => [],
            'freshness' => null,
            'api_budget' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(?int $userId, bool $viewAll): array
    {
        $scope = $viewAll ? null : $userId;

        $counts = $this->repository->statusCounts($scope);
        $failedRecent = $this->repository->failedRecentCount($scope, now()->subDay());

        $jobs = $this->repository->activeJobs($scope, 5);

        $runs = $jobs
            ->map(fn (DataProcessingJob $job): array => $this->runSummary($job))
            ->all();

        $stale = $jobs->contains(fn (DataProcessingJob $job): bool => $job->isStale());

        return [
            'active_count' => ($counts['pending'] ?? 0) + ($counts['processing'] ?? 0),
            'queued_count' => $counts['pending'] ?? 0,
            'processing_count' => $counts['processing'] ?? 0,
            'failed_recent' => $failedRecent,
            'worst_status' => $this->worstStatus($counts, $failedRecent, $stale),
            'runs' => $runs,
            // Reserved slots — populated by CONN/SYNC phases (PIPE-08 §4).
            'freshness' => null,
            'api_budget' => null,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function worstStatus(array $counts, int $failedRecent, bool $stale): string
    {
        if ($failedRecent > 0) {
            return 'error';
        }

        if ($stale) {
            return 'warning';
        }

        if ((($counts['pending'] ?? 0) + ($counts['processing'] ?? 0)) > 0) {
            return 'info';
        }

        return 'success';
    }

    /**
     * @return array<string, mixed>
     */
    private function runSummary(DataProcessingJob $job): array
    {
        $progress = $this->runs->aggregate($job)->progress;

        return [
            'id' => $job->id,
            'name' => $job->displayName(),
            'type' => $job->type->value,
            'type_label' => $job->type->label(),
            'status' => $job->status->value,
            'status_label' => $job->status->label(),
            'entity_icon' => $job->entity_type?->icon(),
            'stage' => $job->stage,
            'percentage' => $progress->percentage,
            'indeterminate' => $progress->indeterminate,
            'eta_seconds' => $progress->etaSeconds,
        ];
    }

    private function cacheKey(?int $userId, bool $viewAll): string
    {
        return self::keyFor($userId, $viewAll);
    }

    private static function keyFor(?int $userId, bool $viewAll): string
    {
        return $viewAll ? 'pipeline:status:all' : 'pipeline:status:user:'.$userId;
    }
}
