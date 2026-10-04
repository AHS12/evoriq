<?php

use App\Enums\SyncEntityType;
use App\Services\Sync\Handlers\ClientSyncHandler;
use App\Services\Sync\Handlers\CustomFieldSyncHandler;
use App\Services\Sync\Handlers\ProjectSyncHandler;
use App\Services\Sync\Handlers\TagSyncHandler;
use App\Services\Sync\Handlers\TaskSyncHandler;
use App\Services\Sync\Handlers\TimeEntryCfValueSyncHandler;
use App\Services\Sync\Handlers\TimeEntryRateSyncHandler;
use App\Services\Sync\Handlers\TimeEntrySyncHandler;
use App\Services\Sync\Handlers\UserGroupSyncHandler;
use App\Services\Sync\Handlers\UserSyncHandler;
use App\Services\Sync\Handlers\WorkspaceSyncHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | Clockify exposes separate REST and Reports APIs. Workspace data is
    | pulled from the REST API, while aggregated reports use the dedicated
    | Reports API host. The add-on token variant shares the REST host.
    |
    */

    'base_url' => env('CLOCKIFY_API_URL', 'https://api.clockify.me/api/v1'),

    'reports_url' => env('CLOCKIFY_REPORTS_URL', 'https://reports.api.clockify.me/v1'),

    'addon_base_url' => env('CLOCKIFY_ADDON_API_URL', 'https://api.clockify.me/api/v1'),

    /*
    |--------------------------------------------------------------------------
    | Request Defaults
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('CLOCKIFY_TIMEOUT', 30),

    'retry' => [
        'times' => (int) env('CLOCKIFY_RETRY_TIMES', 3),

        'sleep' => (int) env('CLOCKIFY_RETRY_SLEEP', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Clockify currently allows 50 requests per second per add-on on a single
    | workspace. Different credentials or endpoints may be constrained
    | differently, so these values are intentionally configurable rather
    | than hard-coded. The limiter should always run conservatively below
    | the known source limit.
    |
    */

    'rate_limit' => [
        'requests_per_second' => (int) env('CLOCKIFY_RATE_LIMIT_RPS', 50),

        'burst_limit' => (int) env('CLOCKIFY_RATE_LIMIT_BURST', 50),

        'cooldown' => (float) env('CLOCKIFY_RATE_LIMIT_COOLDOWN', 0),

        // Free workspaces are limited to 30 requests/hour per workspace.
        'free_requests_per_hour' => (int) env('CLOCKIFY_FREE_REQUESTS_PER_HOUR', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Budget Accounting (SYNC-02)
    |--------------------------------------------------------------------------
    |
    | Clockify exposes no rate-limit headers, so Evoriq accounts for the budget
    | itself in `clockify_api_usage`. We never spend the last slice of a window:
    | the effective limit is the plan limit multiplied by the safety factor.
    |
    */

    'budget_safety_factor' => (float) env('CLOCKIFY_BUDGET_SAFETY_FACTOR', 0.9),

    // Longest a worker will block waiting for a window to reset before the
    // orchestrator is expected to have deferred the work (SYNC-20).
    'budget_wait_max_seconds' => (int) env('CLOCKIFY_BUDGET_WAIT_MAX_SECONDS', 3600),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Clockify list endpoints accept "page" and "page-size" parameters and
    | advertise the final page through the "Last-Page" response header.
    | Pagination is always handled independently of synchronization plans.
    |
    */

    'pagination' => [
        'page_size' => (int) env('CLOCKIFY_PAGE_SIZE', 200),

        'max_page_size' => (int) env('CLOCKIFY_MAX_PAGE_SIZE', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Planner (SYNC-03)
    |--------------------------------------------------------------------------
    |
    | The planner turns a requested range into ordered, budget-aware jobs.
    | History is clamped to `max_history_years`; facts are partitioned into
    | windows that start at `partition_days` and shrink when a partition is
    | estimated to exceed `partition_max_items` per job.
    |
    */

    'planner' => [
        'max_history_years' => (int) env('CLOCKIFY_PLANNER_MAX_HISTORY_YEARS', 5),

        'partition_days' => (int) env('CLOCKIFY_PLANNER_PARTITION_DAYS', 31),

        'partition_max_items' => (int) env('CLOCKIFY_PLANNER_PARTITION_MAX_ITEMS', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Raw Record Store (SYNC-05)
    |--------------------------------------------------------------------------
    |
    | Raw upstream payloads are the recovery layer and the largest table. A
    | retention window is defined here; pruning is executed by OPS-03. `0`
    | keeps every record.
    |
    */

    'raw_records' => [
        'retention_days' => (int) env('CLOCKIFY_RAW_RECORDS_RETENTION_DAYS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity Changes feed (SYNC-06)
    |--------------------------------------------------------------------------
    |
    | Clockify's Entity Changes API is experimental; a config flag lets us
    | disable the incremental feed without a code deploy. `limit` is the page
    | size for the feed's own page/limit pagination.
    |
    */

    'change_feed' => [
        'enabled' => (bool) env('CLOCKIFY_CHANGE_FEED_ENABLED', true),

        'limit' => (int) env('CLOCKIFY_CHANGE_FEED_LIMIT', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync run orchestration (SYNC-09)
    |--------------------------------------------------------------------------
    |
    | The maximum number of sync jobs dispatched in one wave. The API budget
    | remains the hard limit; this only avoids flooding the heavy channel.
    |
    */

    'sync_concurrency' => (int) env('CLOCKIFY_SYNC_CONCURRENCY', 2),

    /*
    |--------------------------------------------------------------------------
    | Sync job reliability (SYNC-13)
    |--------------------------------------------------------------------------
    |
    | A sync job never blocks waiting for the API budget: when the window is
    | exhausted it is parked and the run resumes at the next reset. Transient
    | failures retry with exponential backoff up to `max_attempts`; a job whose
    | worker was lost is reaped once its heartbeat is older than `stale_after`.
    |
    */

    'sync_job' => [
        'max_attempts' => (int) env('CLOCKIFY_SYNC_MAX_ATTEMPTS', 5),
        'retry_base_seconds' => (int) env('CLOCKIFY_SYNC_RETRY_BASE_SECONDS', 60),
        'retry_max_seconds' => (int) env('CLOCKIFY_SYNC_RETRY_MAX_SECONDS', 3600),
        'stale_after' => (int) env('CLOCKIFY_SYNC_STALE_AFTER', 900),
        // Low-priority (reconciliation) work only starts with this much of the
        // budget window still free (SYNC-20 starvation avoidance).
        'low_priority_min_free_ratio' => (float) env('CLOCKIFY_SYNC_LOW_PRIORITY_MIN_FREE_RATIO', 0.2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Entity handlers (ENT-00)
    |--------------------------------------------------------------------------
    |
    | Maps each `SyncEntityType` value to the `SyncHandler` that ingests it.
    | Adding an entity is one handler class plus one entry here; the runner and
    | registry never change.
    |
    */

    'handlers' => [
        SyncEntityType::WORKSPACE->value => WorkspaceSyncHandler::class,
        SyncEntityType::USER->value => UserSyncHandler::class,
        SyncEntityType::CLIENTS->value => ClientSyncHandler::class,
        SyncEntityType::PROJECTS->value => ProjectSyncHandler::class,
        SyncEntityType::TASKS->value => TaskSyncHandler::class,
        SyncEntityType::TAGS->value => TagSyncHandler::class,
        SyncEntityType::TIME_ENTRY->value => TimeEntrySyncHandler::class,
        SyncEntityType::TIME_ENTRY_RATE->value => TimeEntryRateSyncHandler::class,
        SyncEntityType::CUSTOM_FIELDS->value => CustomFieldSyncHandler::class,
        SyncEntityType::TIME_ENTRY_CUSTOM_FIELD_VALUE->value => TimeEntryCfValueSyncHandler::class,
        SyncEntityType::USER_GROUPS->value => UserGroupSyncHandler::class,
    ],

];
