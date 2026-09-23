<?php

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
    ],

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

];
