<?php

namespace App\Enums;

/**
 * A Clockify workspace user's status (ENT-02). Values mirror the API so they
 * can be stored verbatim; unknown values are tolerated as null.
 */
enum ClockifyUserStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case DECLINED = 'DECLINED';
    case INACTIVE = 'INACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::ACTIVE => __('Active'),
            self::DECLINED => __('Declined'),
            self::INACTIVE => __('Inactive'),
        };
    }

    /**
     * Resolve an API status value case-insensitively, tolerating unknown ones.
     */
    public static function fromApi(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtoupper($value)) : null;
    }
}
