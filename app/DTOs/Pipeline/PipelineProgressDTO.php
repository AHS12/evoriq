<?php

namespace App\DTOs\Pipeline;

/**
 * The server-computed progress of a run (PIPE-02).
 */
final readonly class PipelineProgressDTO
{
    public function __construct(
        public ?int $total,
        public ?int $processed,
        public int $percentage,
        public bool $indeterminate,
        public int $elapsedSeconds,
        public ?int $etaSeconds,
        public ?int $throughputPerMin,
    ) {}
}
