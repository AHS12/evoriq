<?php

namespace App\Enums;

/**
 * The kind of background run a pipeline event belongs to.
 *
 * `run_id` is a string so different run tables (data processing jobs today,
 * Clockify sync runs later) can share one append-only event stream.
 */
enum PipelineRunType: string
{
    case DATA_PROCESSING = 'data_processing';
    case SYNC = 'sync';

    public function label(): string
    {
        return match ($this) {
            self::DATA_PROCESSING => __('Data processing'),
            self::SYNC => __('Sync'),
        };
    }

    /**
     * The lucide-react icon name used by the frontend.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DATA_PROCESSING => 'workflow',
            self::SYNC => 'refresh-cw',
        };
    }
}
