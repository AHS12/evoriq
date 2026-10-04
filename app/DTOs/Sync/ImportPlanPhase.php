<?php

namespace App\DTOs\Sync;

use App\Enums\SyncPhase;

/**
 * One ordered phase of an import plan (SYNC-03): reference → fact → derive.
 */
final readonly class ImportPlanPhase
{
    /**
     * @param  array<int, ImportPlanJob>  $jobs
     */
    public function __construct(
        public SyncPhase $phase,
        public array $jobs,
    ) {}

    public function estimatedRequests(): int
    {
        return array_sum(array_map(
            static fn (ImportPlanJob $job): int => $job->estimatedRequests,
            $this->jobs,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'phase' => $this->phase->value,
            'phase_label' => $this->phase->label(),
            'job_count' => count($this->jobs),
            'estimated_requests' => $this->estimatedRequests(),
            'jobs' => array_map(
                static fn (ImportPlanJob $job): array => $job->toArray(),
                $this->jobs,
            ),
        ];
    }
}
