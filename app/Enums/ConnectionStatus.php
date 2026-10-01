<?php

namespace App\Enums;

/**
 * The lifecycle status of a Clockify connection (CONN-01).
 */
enum ConnectionStatus: string
{
    case ACTIVE = 'active';
    case INVALID = 'invalid';
    case DISABLED = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Active'),
            self::INVALID => __('Invalid'),
            self::DISABLED => __('Disabled'),
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
