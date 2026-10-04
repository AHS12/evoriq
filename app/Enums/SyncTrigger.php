<?php

namespace App\Enums;

/**
 * What kicked off a sync run (SYNC-01).
 */
enum SyncTrigger: string
{
    case MANUAL = 'manual';
    case SCHEDULED = 'scheduled';
    case WEBHOOK = 'webhook';
    case RECONCILIATION = 'reconciliation';
    case INITIAL_IMPORT = 'initial_import';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => __('Manual'),
            self::SCHEDULED => __('Scheduled'),
            self::WEBHOOK => __('Webhook'),
            self::RECONCILIATION => __('Reconciliation'),
            self::INITIAL_IMPORT => __('Initial import'),
        };
    }
}
