<?php

namespace App\Enums;

/**
 * The granularity of an API usage budget window (SYNC-01, SYNC-02): Free plans
 * are limited per hour, paid plans per second.
 */
enum ApiUsageWindowType: string
{
    case HOUR = 'hour';
    case SECOND = 'second';

    public function label(): string
    {
        return match ($this) {
            self::HOUR => __('Per hour'),
            self::SECOND => __('Per second'),
        };
    }

    public function isHourly(): bool
    {
        return $this === self::HOUR;
    }
}
