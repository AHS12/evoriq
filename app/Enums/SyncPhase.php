<?php

namespace App\Enums;

/**
 * The ordering phase of a sync job (SYNC-01, SYNC-03): reference dimensions
 * load before facts, and derived work runs last.
 */
enum SyncPhase: string
{
    case REFERENCE = 'reference';
    case FACT = 'fact';
    case DERIVE = 'derive';

    public function label(): string
    {
        return match ($this) {
            self::REFERENCE => __('Reference'),
            self::FACT => __('Fact'),
            self::DERIVE => __('Derived'),
        };
    }
}
