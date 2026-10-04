<?php

namespace App\Enums;

/**
 * The scheduling priority of a sync run (SYNC-01, SYNC-20). Priority affects
 * ordering only — never the shared rate budget.
 */
enum SyncPriority: string
{
    case HIGH = 'high';
    case NORMAL = 'normal';
    case LOW = 'low';

    public function label(): string
    {
        return match ($this) {
            self::HIGH => __('High'),
            self::NORMAL => __('Normal'),
            self::LOW => __('Low'),
        };
    }

    /**
     * The queue channel this priority dispatches to (SYNC-20). Higher priority
     * work lands on a channel the worker drains first, so manual/webhook work
     * precedes daily, which precedes low-priority reconciliation.
     */
    public function queue(): QueueName
    {
        return match ($this) {
            self::HIGH => QueueName::CRITICAL,
            self::NORMAL => QueueName::DEFAULT,
            self::LOW => QueueName::HEAVY,
        };
    }

    /**
     * Higher-priority levels than this one (for starvation avoidance).
     *
     * @return array<int, self>
     */
    public function higherLevels(): array
    {
        return match ($this) {
            self::HIGH => [],
            self::NORMAL => [self::HIGH],
            self::LOW => [self::HIGH, self::NORMAL],
        };
    }
}
