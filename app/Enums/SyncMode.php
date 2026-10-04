<?php

namespace App\Enums;

/**
 * The scope of a sync run (SYNC-01): a full history import, a targeted
 * incremental pull, or a rolling reconciliation window.
 */
enum SyncMode: string
{
    case INITIAL = 'initial';
    case INCREMENTAL = 'incremental';
    case RECONCILIATION = 'reconciliation';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => __('Initial'),
            self::INCREMENTAL => __('Incremental'),
            self::RECONCILIATION => __('Reconciliation'),
        };
    }
}
