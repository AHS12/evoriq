<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feed
    |--------------------------------------------------------------------------
    |
    | Notifications are surfaced to the UI through the shared Inertia
    | `notifications` prop and refreshed by client polling. Adjust the client
    | interval in `resources/js/hooks/use-notification-poll.ts`.
    |
    */

    'feed' => [
        /** Number of notifications shared with the bell on every page. */
        'recent_limit' => (int) env('NOTIFICATION_RECENT_LIMIT', 8),

        /** Default per-page size for the full feed. */
        'per_page' => (int) env('NOTIFICATION_PER_PAGE', 20),

        /** Maximum allowed per-page size. */
        'max_per_page' => (int) env('NOTIFICATION_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | The daily `notifications:prune` command reads the global
    | `NOTIFICATION_RETENTION_DAYS` setting; this value is only the fallback
    | when that setting is unavailable.
    |
    */

    'retention' => [
        'days' => (int) env('NOTIFICATION_RETENTION_DAYS', 90),
        'chunk' => (int) env('NOTIFICATION_RETENTION_CHUNK', 500),
    ],

];
