<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('data-processing:cleanup-completed')->daily();

// Application health: run the checks and keep the schedule heartbeat fresh.
Schedule::command('health:check')->everyFiveMinutes();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();

// Pulse server metrics (CPU, memory, disk) for the dashboard.
// `pulse:check` is a long-lived daemon, so run it with --once here; scheduling
// it without --once leaks a process (and a DB connection) every minute.
Schedule::command('pulse:check --once')->everyMinute();
