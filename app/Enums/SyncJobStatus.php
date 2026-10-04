<?php

namespace App\Enums;

/**
 * The lifecycle status of a single sync job (SYNC-01). `retry_scheduled` is a
 * waiting state, not a terminal one (SYNC-13).
 */
enum SyncJobStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case RETRY_SCHEDULED = 'retry_scheduled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::RUNNING => __('Running'),
            self::COMPLETED => __('Completed'),
            self::FAILED => __('Failed'),
            self::CANCELLED => __('Cancelled'),
            self::RETRY_SCHEDULED => __('Retry scheduled'),
        };
    }

    /**
     * Whether the job has reached a terminal state.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELLED], true);
    }

    /**
     * Whether the job is still pending, running or waiting to retry.
     */
    public function isActive(): bool
    {
        return ! $this->isFinal();
    }
}
