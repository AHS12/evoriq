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
    | Run liveness, ETA & timeline
    |--------------------------------------------------------------------------
    |
    | `stale_after` is the number of seconds without a heartbeat before a
    | running job is reported stale — keep it equal to `exports.stale_after`
    | (the reaper's threshold). `eta_min_samples` is the minimum number of
    | progress points in the current stage before an ETA is estimated.
    | `timeline_limit` bounds the events embedded in a run resource.
    |
    */

    'stale_after' => (int) env('PIPELINE_STALE_AFTER', (int) env('EXPORT_STALE_AFTER', 1920)),

    'eta_min_samples' => (int) env('PIPELINE_ETA_MIN_SAMPLES', 2),

    'timeline_limit' => (int) env('PIPELINE_TIMELINE_LIMIT', 50),

    /*
    |--------------------------------------------------------------------------
    | Timeline paging
    |--------------------------------------------------------------------------
    |
    | Number of events returned per window on the run timeline (`/activity/{job}`):
    | the initial tail, an append (`after_sequence`) or an older page
    | (`before_sequence`).
    |
    */

    'timeline_page_size' => (int) env('PIPELINE_TIMELINE_PAGE_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Event context size
    |--------------------------------------------------------------------------
    |
    | Maximum JSON size (bytes) of an event's `context` before the API sends a
    | truncated string preview instead (PIPE-06). Keeps polled timelines small.
    |
    */

    'context_limit' => (int) env('PIPELINE_CONTEXT_LIMIT', 20000),

    /*
    |--------------------------------------------------------------------------
    | Retry backoff
    |--------------------------------------------------------------------------
    |
    | Base delay (seconds) used to compute `next_retry_at` when a retry is
    | scheduled (multiplied by the attempt number).
    |
    */

    'retry_base_seconds' => (int) env('PIPELINE_RETRY_BASE_SECONDS', 60),

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
