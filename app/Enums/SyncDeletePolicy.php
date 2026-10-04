<?php

namespace App\Enums;

/**
 * How an entity reacts to an upstream deletion (SYNC-07, ENT-00). Dimensions and
 * facts keep their history (soft), join tables remove their rows (replace), and
 * entities that cannot be deleted upstream opt out (none).
 */
enum SyncDeletePolicy: string
{
    case SOFT = 'soft';
    case REPLACE = 'replace';
    case NONE = 'none';

    public function label(): string
    {
        return match ($this) {
            self::SOFT => __('Soft delete'),
            self::REPLACE => __('Replace'),
            self::NONE => __('Keep'),
        };
    }
}
