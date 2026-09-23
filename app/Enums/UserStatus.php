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
            self::INVITED => 'Invited',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
        };
    }
}
