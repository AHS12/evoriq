<?php

namespace App\DTOs\Pipeline;

use App\Enums\PipelineEventLevel;
use App\Enums\PipelineEventType;
use App\Enums\PipelineRunType;
use App\Services\Pipeline\PipelineEventRecorder;
use Carbon\CarbonInterface;

/**
 * The typed shape of one pipeline event as it crosses into the repository.
 *
 * Built by {@see PipelineEventRecorder} (there is no
 * HTTP request for this stream yet).
 */
final readonly class PipelineEventDTO
{
    /**
     * @param  array<string, mixed>  $context  Structured, already-redacted details.
     * @param  array{total?: int|float|null, processed?: int|float|null, percentage?: float|null}  $progress
     */
    public function __construct(
        public PipelineRunType $runType,
        public string $runId,
        public PipelineEventType $type,
        public PipelineEventLevel $level = PipelineEventLevel::INFO,
        public ?string $stage = null,
        public ?string $message = null,
        public array $context = [],
        public array $progress = [],
        public int $attempt = 1,
        public ?int $durationMs = null,
        public ?CarbonInterface $occurredAt = null,
        public int|string|null $organizationId = null,
        public ?int $sequence = null,
    ) {}

    /**
     * Return a copy with the sequence allocated by the repository.
     */
    public function withSequence(int $sequence): self
    {
        return new self(
            runType: $this->runType,
            runId: $this->runId,
            type: $this->type,
            level: $this->level,
            stage: $this->stage,
            message: $this->message,
            context: $this->context,
            progress: $this->progress,
            attempt: $this->attempt,
            durationMs: $this->durationMs,
            occurredAt: $this->occurredAt,
            organizationId: $this->organizationId,
            sequence: $sequence,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'organization_id' => $this->organizationId,
            'run_type' => $this->runType->value,
            'run_id' => $this->runId,
            'sequence' => $this->sequence,
            'type' => $this->type->value,
            'level' => $this->level->value,
            'stage' => $this->stage,
            'message' => $this->message,
            'context' => $this->context === [] ? null : $this->context,
            'progress' => $this->progress === [] ? null : $this->progress,
            'attempt' => $this->attempt,
            'duration_ms' => $this->durationMs,
            'occurred_at' => ($this->occurredAt ?? now())->toDateTimeString(),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
