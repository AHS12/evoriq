<?php

namespace App\Enums;

/**
 * The severity of a pipeline event. Drives filtering and the colour of the
 * timeline marker in the run views (PIPE-05/06).
 */
enum PipelineEventLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case SUCCESS = 'success';
    case WARNING = 'warning';
    case ERROR = 'error';

    public function label(): string
    {
        return match ($this) {
            self::DEBUG => __('Debug'),
            self::INFO => __('Info'),
            self::SUCCESS => __('Success'),
            self::WARNING => __('Warning'),
            self::ERROR => __('Error'),
        };
    }

    /**
     * The lucide-react icon name used by the frontend.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DEBUG => 'bug',
            self::INFO => 'info',
            self::SUCCESS => 'circle-check',
            self::WARNING => 'triangle-alert',
            self::ERROR => 'circle-alert',
        };
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }
}
