<?php

namespace App\DTOs\Pipeline;

/**
 * A group of identical issues from a run (PIPE-06): the import errors collapsed
 * by `type + message` with a count and a few sample rows.
 */
final readonly class PipelineIssueGroupDTO
{
    /**
     * @param  array<int, array{row: int, message: string}>  $sampleRows
     */
    public function __construct(
        public string $type,
        public string $message,
        public int $count,
        public array $sampleRows,
        public ?string $firstSeen,
        public ?string $lastSeen,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'count' => $this->count,
            'sample_rows' => $this->sampleRows,
            'first_seen' => $this->firstSeen,
            'last_seen' => $this->lastSeen,
        ];
    }
}
