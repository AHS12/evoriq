<?php

namespace App\DTOs\Pipeline;

use App\Models\PipelineEvent;
use Illuminate\Support\Collection;

/**
 * A bounded window of a run's event stream (PIPE-05): the events plus the
 * metadata the timeline UI needs to load older history.
 */
final readonly class PipelineEventWindowDTO
{
    /**
     * @param  Collection<int, PipelineEvent>  $events
     */
    public function __construct(
        public Collection $events,
        public ?int $oldestSequence,
        public ?int $latestSequence,
        public bool $hasMoreOlder,
    ) {}

    /**
     * @return array{oldest_sequence: int|null, latest_sequence: int|null, has_more_older: bool}
     */
    public function meta(): array
    {
        return [
            'oldest_sequence' => $this->oldestSequence,
            'latest_sequence' => $this->latestSequence,
            'has_more_older' => $this->hasMoreOlder,
        ];
    }
}
