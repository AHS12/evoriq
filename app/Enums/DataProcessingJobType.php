<?php

namespace App\Enums;

enum DataProcessingJobType: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';

    public function label(): string
    {
        return match ($this) {
            self::IMPORT => 'Import',
            self::EXPORT => 'Export',
        };
    }
}
