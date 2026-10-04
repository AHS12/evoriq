<?php

namespace App\Enums;

/**
 * The kind of change reported by Clockify's Entity Changes feed (SYNC-01,
 * SYNC-06).
 */
enum EntityChangeType: string
{
    case CREATED = 'CREATED';
    case UPDATED = 'UPDATED';
    case DELETED = 'DELETED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => __('Created'),
            self::UPDATED => __('Updated'),
            self::DELETED => __('Deleted'),
        };
    }
}
