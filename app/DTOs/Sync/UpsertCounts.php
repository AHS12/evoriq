<?php

namespace App\DTOs\Sync;

/**
 * The outcome of an idempotent upsert batch (SYNC-08): how many rows were
 * created, genuinely changed, or left untouched. The sync runner accumulates
 * these onto the job/run counters.
 */
final readonly class UpsertCounts
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $unchanged = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            created: $this->created + $other->created,
            updated: $this->updated + $other->updated,
            unchanged: $this->unchanged + $other->unchanged,
        );
    }

    /**
     * Total rows the batch touched.
     */
    public function processed(): int
    {
        return $this->created + $this->updated + $this->unchanged;
    }

    /**
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
        ];
    }
}
