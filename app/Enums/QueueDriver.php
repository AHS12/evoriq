<?php

namespace App\Enums;

use App\Enums\Concerns\HasDriverOptions;

enum QueueDriver: string
{
    use HasDriverOptions;

    case SYNC = 'sync';
    case DATABASE = 'database';
    case REDIS = 'redis';

    public function label(): string
    {
        return match ($this) {
            self::SYNC => 'Sync (no worker)',
            self::DATABASE => 'Database',
            self::REDIS => 'Redis',
        };
    }
}
