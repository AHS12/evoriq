<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Export Storage
    |--------------------------------------------------------------------------
    |
    | The disk and base directory used to persist generated export files.
    | `disk` must reference a configured filesystem disk from filesystems.php.
    |
    */

    'disk' => env('EXPORT_DISK', 'local'),

    'base_path' => env('EXPORT_BASE_PATH', 'exports'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default page size used when listing data processing jobs.
    |
    */

    'pagination' => (int) env('EXPORT_PAGINATION', 15),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Number of days completed jobs (and their files) are kept before the
    | `data-processing:cleanup-completed` command removes them.
    |
    */

    'cleanup_days' => (int) env('EXPORT_CLEANUP_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Imports
    |--------------------------------------------------------------------------
    |
    | The number of rows read per chunk while importing. Smaller chunks give
    | more frequent progress updates.
    |
    */

    'import' => [
        'chunk_size' => (int) env('EXPORT_IMPORT_CHUNK_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stalled jobs
    |--------------------------------------------------------------------------
    |
    | A running job whose worker was lost (crash, OOM, timeout) is failed by
    | `data-processing:reap-stale` once it has been processing for this many
    | seconds. Keep it above the heavy queue timeout.
    |
    */

    'stale_after' => (int) env('EXPORT_STALE_AFTER', 1920),

];
