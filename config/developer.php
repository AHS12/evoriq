<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Developer Maintenance Actions
    |--------------------------------------------------------------------------
    |
    | When enabled, super admins can run cache/config/route/view clearing and
    | the settings/permission sync commands from Administration → Settings →
    | Developer. Keep this disabled in production unless you explicitly need
    | in-app maintenance.
    |
    */

    'maintenance_actions' => env('DEVELOPER_MAINTENANCE_ACTIONS', false),

];
