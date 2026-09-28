<?php

namespace App\Enums;

use App\Services\Pipeline\FailureReasonResolver;

/**
 * A friendly classification of *why* a run failed, with a hint and a suggested
 * action the UI can offer (PIPE-06/07). Derived from the exception or message
 * by {@see FailureReasonResolver}.
 */
enum PipelineFailureReason: string
{
    case API_UNAVAILABLE = 'api_unavailable';
    case RATE_LIMITED = 'rate_limited';
    case AUTH_FAILED = 'auth_failed';
    case NETWORK = 'network';
    case TIMEOUT = 'timeout';
    case VALIDATION = 'validation';
    case SOURCE_MISSING = 'source_missing';
    case WORKER_LOST = 'worker_lost';
    case CANCELLED = 'cancelled';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::API_UNAVAILABLE => __('Service unavailable'),
            self::RATE_LIMITED => __('Rate limited'),
            self::AUTH_FAILED => __('Authentication failed'),
            self::NETWORK => __('Network error'),
            self::TIMEOUT => __('Timed out'),
            self::VALIDATION => __('Invalid data'),
            self::SOURCE_MISSING => __('Source file missing'),
            self::WORKER_LOST => __('Worker lost'),
            self::CANCELLED => __('Cancelled'),
            self::UNKNOWN => __('Failed'),
        };
    }

    /**
     * A short, user-facing explanation of what happened.
     */
    public function hint(): string
    {
        return match ($this) {
            self::API_UNAVAILABLE => __('The external service could not be reached.'),
            self::RATE_LIMITED => __('Too many requests were sent. Wait a moment and try again.'),
            self::AUTH_FAILED => __('The connected account was rejected — reconnect it.'),
            self::NETWORK => __('A connection error interrupted the job.'),
            self::TIMEOUT => __('The job exceeded its time limit.'),
            self::VALIDATION => __('Some rows or parameters were rejected.'),
            self::SOURCE_MISSING => __('The source file is no longer available.'),
            self::WORKER_LOST => __('The worker running this job stopped unexpectedly.'),
            self::CANCELLED => __('The job was cancelled before it finished.'),
            self::UNKNOWN => __('Something went wrong. Open the job for details.'),
        };
    }

    /**
     * The suggested call to action: `retry`, `reconnect`, `review`, `upload`
     * or `null` when retrying is not useful.
     */
    public function action(): ?string
    {
        return match ($this) {
            self::AUTH_FAILED => 'reconnect',
            self::VALIDATION => 'review',
            self::SOURCE_MISSING => 'upload',
            self::CANCELLED => null,
            default => 'retry',
        };
    }
}
