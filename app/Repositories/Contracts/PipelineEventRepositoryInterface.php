<?php

namespace App\Repositories\Contracts;

use App\Enums\PipelineRunType;
use App\Models\PipelineEvent;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PipelineEventRepositoryInterface
{
    /**
     * Insert one event. The sequence must already be allocated.
     *
     * @param  array<string, mixed>  $data
     */
    public function append(array $data): PipelineEvent;

    /**
     * The next sequence number for a run (max + 1). Callers must guard the
     * insert against the unique constraint for race safety.
     */
    public function nextSequence(PipelineRunType $type, string|int $runId): int;

    /**
     * The most recent event of any type for a run.
     */
    public function latestForRun(PipelineRunType $type, string|int $runId): ?PipelineEvent;

    /**
     * The most recent PROGRESS event for a run (drives coalescing).
     */
    public function latestProgressForRun(PipelineRunType $type, string|int $runId): ?PipelineEvent;

    /**
     * A run's events, oldest first. Bounded when `$limit > 0`.
     *
     * @return Collection<int, PipelineEvent>
     */
    public function allForRun(PipelineRunType $type, string|int $runId, int $limit = 0): Collection;

    /**
     * A run's events, oldest first, for the timeline views.
     *
     * Supported filters: `level`, `type`, `stage`, `after_sequence`, `search`,
     * `order_direction`.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PipelineEvent>
     */
    public function forRun(PipelineRunType $type, string|int $runId, int $perPage = 50, array $filters = []): LengthAwarePaginator;

    /**
     * The latest event for each of the given runs (global indicator).
     *
     * @param  array<int, array{run_type: PipelineRunType|string, run_id: string|int}>  $runKeys
     * @return array<string, PipelineEvent> keyed by "{run_type}:{run_id}"
     */
    public function latestForRuns(array $runKeys): array;

    /**
     * The newest `$limit` events for a run, returned oldest-first (a tail
     * window for the run timeline).
     *
     * @return Collection<int, PipelineEvent>
     */
    public function tailForRun(PipelineRunType $type, string|int $runId, int $limit): Collection;

    /**
     * Events newer than `$after`, oldest-first, bounded by `$limit` (append).
     *
     * @return Collection<int, PipelineEvent>
     */
    public function afterSequence(PipelineRunType $type, string|int $runId, int $after, int $limit): Collection;

    /**
     * Events older than `$before`, returned oldest-first, bounded by `$limit`
     * (prepend).
     *
     * @return Collection<int, PipelineEvent>
     */
    public function beforeSequence(PipelineRunType $type, string|int $runId, int $before, int $limit): Collection;

    /**
     * Whether any event exists older than `$sequence`.
     */
    public function existsBefore(PipelineRunType $type, string|int $runId, int $sequence): bool;

    /**
     * Delete events older than the cutoff. Returns the number deleted.
     */
    public function pruneBefore(CarbonInterface $cutoff): int;
}
