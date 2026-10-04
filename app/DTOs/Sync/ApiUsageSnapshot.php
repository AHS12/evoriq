<?php

namespace App\DTOs\Sync;

use App\Enums\ApiUsageWindowType;
use Carbon\CarbonInterface;

/**
 * A read-only view of the current API budget window (SYNC-02). Consumed by the
 * client's budget guard and surfaced to the UI by SYNC-17.
 */
final readonly class ApiUsageSnapshot
{
    public function __construct(
        public ApiUsageWindowType $windowType,
        public int $used,
        public int $limit,
        public int $remaining,
        public CarbonInterface $windowEndsAt,
        public int $resetsIn,
        public ?CarbonInterface $lastRequestAt = null,
    ) {}

    /**
     * Whether the window has no headroom left.
     */
    public function isExhausted(): bool
    {
        return $this->remaining <= 0;
    }

    /**
     * Whether the remaining budget is below the given fraction of the limit.
     */
    public function isLow(float $threshold = 0.2): bool
    {
        return $this->limit > 0 && ($this->remaining / $this->limit) < $threshold;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'window_type' => $this->windowType->value,
            'used' => $this->used,
            'limit' => $this->limit,
            'remaining' => $this->remaining,
            'resets_at' => $this->windowEndsAt->toIso8601String(),
            'resets_in' => $this->resetsIn,
            'last_request_at' => $this->lastRequestAt?->toIso8601String(),
        ];
    }
}
