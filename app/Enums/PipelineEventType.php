<?php

namespace App\Enums;

/**
 * Every meaningful transition a background run can emit into the pipeline
 * event stream. The set is intentionally generic so imports, exports and
 * (later) Clockify syncs share one vocabulary.
 */
enum PipelineEventType: string
{
    case DISPATCHED = 'dispatched';
    case STARTED = 'started';
    case STAGE_STARTED = 'stage_started';
    case PROGRESS = 'progress';
    case STAGE_COMPLETED = 'stage_completed';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
    case RETRY_SCHEDULED = 'retry_scheduled';
    case RETRY_STARTED = 'retry_started';
    case ARTIFACT_READY = 'artifact_ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::DISPATCHED => __('Dispatched'),
            self::STARTED => __('Started'),
            self::STAGE_STARTED => __('Stage started'),
            self::PROGRESS => __('Progress'),
            self::STAGE_COMPLETED => __('Stage completed'),
            self::INFO => __('Info'),
            self::WARNING => __('Warning'),
            self::ERROR => __('Error'),
            self::RETRY_SCHEDULED => __('Retry scheduled'),
            self::RETRY_STARTED => __('Retry started'),
            self::ARTIFACT_READY => __('Artifact ready'),
            self::COMPLETED => __('Completed'),
            self::CANCELLED => __('Cancelled'),
            self::FAILED => __('Failed'),
        };
    }

    /**
     * The lucide-react icon name used by the frontend.
     */
    public function icon(): string
    {
        return match ($this) {
            self::DISPATCHED => 'send',
            self::STARTED => 'play',
            self::STAGE_STARTED => 'circle-play',
            self::PROGRESS => 'loader',
            self::STAGE_COMPLETED => 'circle-check',
            self::INFO => 'info',
            self::WARNING => 'triangle-alert',
            self::ERROR => 'circle-alert',
            self::RETRY_SCHEDULED => 'timer-reset',
            self::RETRY_STARTED => 'rotate-cw',
            self::ARTIFACT_READY => 'file-check',
            self::COMPLETED => 'check',
            self::CANCELLED => 'ban',
            self::FAILED => 'x',
        };
    }

    /**
     * The default level when the recorder is not given one explicitly.
     */
    public function defaultLevel(): PipelineEventLevel
    {
        return match ($this) {
            self::STAGE_COMPLETED,
            self::ARTIFACT_READY,
            self::COMPLETED => PipelineEventLevel::SUCCESS,

            self::WARNING,
            self::RETRY_SCHEDULED,
            self::CANCELLED => PipelineEventLevel::WARNING,

            self::ERROR,
            self::FAILED => PipelineEventLevel::ERROR,

            default => PipelineEventLevel::INFO,
        };
    }

    /**
     * Whether this event closes the run.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::FAILED], true);
    }
}
