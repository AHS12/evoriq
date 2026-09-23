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

];
