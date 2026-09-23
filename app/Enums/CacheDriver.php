<?php

namespace App\Enums;

use App\Enums\Concerns\HasDriverOptions;

enum CacheDriver: string
{
    use HasDriverOptions;

    case FILE = 'file';
    case DATABASE = 'database';
    case REDIS = 'redis';

    public function label(): string
    {
        return match ($this) {
            self::FILE => 'File',
            self::DATABASE => 'Database',
            self::REDIS => 'Redis',
        };
    }
}
