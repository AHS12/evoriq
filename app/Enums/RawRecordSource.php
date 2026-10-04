<?php

namespace App\Enums;

/**
 * Where a raw upstream payload came from (SYNC-01, SYNC-05). Retention and
 * repair tooling can treat each source differently.
 */
enum RawRecordSource: string
{
    case API = 'api';
    case WEBHOOK = 'webhook';
    case CHANGE_FEED = 'change_feed';

    public function label(): string
    {
        return match ($this) {
            self::API => __('API'),
            self::WEBHOOK => __('Webhook'),
            self::CHANGE_FEED => __('Change feed'),
        };
    }
}
