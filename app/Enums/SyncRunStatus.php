<?php

namespace App\Enums;

/**
 * The lifecycle status of a sync run (SYNC-01).
 */
enum SyncRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::RUNNING => __('Running'),
            self::PAUSED => __('Paused'),
            self::COMPLETED => __('Completed'),
            self::FAILED => __('Failed'),
            self::CANCELLED => __('Cancelled'),
        };
    }

    /**
     * Whether the run has reached a terminal state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Whether the run is still waiting, running or paused.
     */
    public function isActive(): bool
    {
        return ! $this->isFinal();
    }
}
