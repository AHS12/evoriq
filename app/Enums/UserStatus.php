<?php

namespace App\Enums;

enum UserStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::INVITED => __('Invited'),
            self::ACTIVE => __('Active'),
            self::SUSPENDED => __('Suspended'),
        };
    }
}
