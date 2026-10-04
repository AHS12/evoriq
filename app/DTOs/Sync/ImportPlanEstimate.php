<?php

namespace App\DTOs\Sync;

use App\Enums\ApiUsageWindowType;

/**
 * The cost/duration estimate for an import plan (SYNC-03), derived from the
 * connection's real budget window.
 */
final readonly class ImportPlanEstimate
{
    public function __construct(
        public int $jobs,
        public int $partitions,
        public int $requests,
        public ApiUsageWindowType $windowType,
        public int $requestsPerWindow,
        public int $windowSeconds,
        public int $estimatedSeconds,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'jobs' => $this->jobs,
            'partitions' => $this->partitions,
            'requests' => $this->requests,
            'window_type' => $this->windowType->value,
            'requests_per_window' => $this->requestsPerWindow,
            'window_seconds' => $this->windowSeconds,
            'estimated_seconds' => $this->estimatedSeconds,
        ];
    }
}
