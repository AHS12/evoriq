<?php

namespace App\Enums;

enum BackupRunType: string
{
    case BACKUP = 'backup';
    case CLEANUP = 'cleanup';
    case MONITOR = 'monitor';

    public function label(): string
    {
        return match ($this) {
            self::BACKUP => 'Backup',
            self::CLEANUP => 'Cleanup',
            self::MONITOR => 'Health check',
        };
    }
}
