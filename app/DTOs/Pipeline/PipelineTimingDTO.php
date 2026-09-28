<?php

namespace App\DTOs\Pipeline;

/**
 * The lifecycle timestamps of a run (PIPE-02).
 */
final readonly class PipelineTimingDTO
{
    public function __construct(
        public ?string $dispatchedAt,
        public ?string $startedAt,
        public ?string $completedAt,
        public ?int $durationMs,
        public ?string $lastHeartbeatAt,
        public bool $stale,
    ) {}
}
