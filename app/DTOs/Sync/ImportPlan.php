<?php

namespace App\DTOs\Sync;

use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use Carbon\CarbonInterface;

/**
 * A deterministic, serializable synchronization plan (SYNC-03). Persisted as a
 * snapshot on `clockify_sync_runs.plan` and rendered by the import review step
 * (PIPE-09).
 */
final readonly class ImportPlan
{
    /**
     * @param  array<int, ImportPlanPhase>  $phases
     */
    public function __construct(
        public SyncMode $mode,
        public SyncPriority $priority,
        public CarbonInterface $rangeStart,
        public CarbonInterface $rangeEnd,
        public int $pageSize,
        public array $phases,
        public ImportPlanEstimate $estimate,
    ) {}

    /**
     * Every planned job, flattened in phase order.
     *
     * @return array<int, ImportPlanJob>
     */
    public function jobs(): array
    {
        $jobs = [];

        foreach ($this->phases as $phase) {
            foreach ($phase->jobs as $job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode->value,
            'priority' => $this->priority->value,
            'range_start' => $this->rangeStart->toIso8601String(),
            'range_end' => $this->rangeEnd->toIso8601String(),
            'page_size' => $this->pageSize,
            'total_jobs' => count($this->jobs()),
            'phases' => array_map(
                static fn (ImportPlanPhase $phase): array => $phase->toArray(),
                $this->phases,
            ),
            'estimate' => $this->estimate->toArray(),
        ];
    }
}
