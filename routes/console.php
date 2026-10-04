<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('data-processing:cleanup-completed')->daily();

// Reliability: fail jobs whose worker was lost (crash, OOM, timeout).
Schedule::command('data-processing:reap-stale')->everyFiveMinutes();

// Reliability: reap lost sync jobs and resume their runs (SYNC-13).
Schedule::command('sync:reap-stale')->everyFiveMinutes();

// Reliability: re-drive budget-parked/retry sync runs once the window resets
// (SYNC-13/SYNC-20) — the safety net if a delayed resume job was lost.
Schedule::command('sync:resume')->everyMinute();

// Retention: prune expired and old in-app notifications.
Schedule::command('notifications:prune')->daily();

// Retention: prune pipeline events beyond the configured window (PIPE-12).
Schedule::command('pipeline:prune-events')->daily();

// Retention: prune audit log entries per each channel's retention window.
Schedule::command('audit:clean')->dailyAt('03:30');

// Backups: the tick evaluates the backup settings and dispatches the backup,
// cleanup and health-check work that is due (DB-driven schedule).
Schedule::command('backup:schedule-tick')
    ->everyFiveMinutes()
    ->runInBackground()
    ->withoutOverlapping();

// Application health: run the checks and keep the schedule heartbeat fresh.
Schedule::command('health:check')->everyFiveMinutes();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();

// Pulse server metrics (CPU, memory, disk) for the dashboard.
// `pulse:check` is a long-lived daemon, so run it with --once here; scheduling
// it without --once leaks a process (and a DB connection) every minute.
Schedule::command('pulse:check --once')->everyMinute();
