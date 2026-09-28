<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Progress event coalescing
    |--------------------------------------------------------------------------
    |
    | A chunked import or sync can report progress thousands of times. The
    | recorder only persists a PROGRESS event when the stage changed, when the
    | completion percentage crosses one of these percentage buckets, or when
    | at least this many seconds have passed since the run's last progress
    | event. This keeps the timeline readable without losing the signal.
    |
    */

    'progress_event_bucket' => (int) env('PIPELINE_PROGRESS_EVENT_BUCKET', 5),

    'progress_event_min_seconds' => (int) env('PIPELINE_PROGRESS_EVENT_MIN_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Number of days pipeline events are kept before the scheduled prune
    | (PIPE-12) removes them.
    |
    */

    'event_retention_days' => (int) env('PIPELINE_EVENT_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Redaction
    |--------------------------------------------------------------------------
    |
    | Context keys (matched case-insensitively at any nesting depth) that are
    | removed from an event's context before it is persisted, so credentials
    | never reach the event stream.
    |
    */

    'redacted_attributes' => [
        'api_key',
        'token',
        'authorization',
        'password',
        'secret',
    ],

];
