<?php

namespace App\DTOs\Pipeline;

/**
 * One stage derived from a run's event stream (PIPE-02).
 *
 * `status` is one of `pending`, `running`, `completed`, `failed`, `cancelled`.
 */
final readonly class PipelineStageDTO
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public ?string $startedAt,
        public ?string $endedAt,
        public ?int $durationMs,
        public ?int $processed,
        public ?int $total,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'started_at' => $this->startedAt,
            'ended_at' => $this->endedAt,
            'duration_ms' => $this->durationMs,
            'processed' => $this->processed,
            'total' => $this->total,
        ];
    }
}
