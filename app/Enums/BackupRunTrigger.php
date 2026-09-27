<?php

namespace App\Enums;

enum BackupRunTrigger: string
{
    case SCHEDULED = 'scheduled';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Scheduled',
            self::MANUAL => 'Manual',
        };
    }
}
