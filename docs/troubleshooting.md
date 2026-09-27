# Troubleshooting

Common problems and their fixes.

## Setup

- **`/setup` redirects or errors before the wizard renders** — confirm
  `SESSION_DRIVER` is `file` (or Redis is running). Safe defaults live in
  `.env.example`.
- **Setup completed but you need it again** — `php artisan setup:reset`
  (keeps users) or `--fresh` (deletes users). Development only; use `--force`
  in production.
- **Config changes not applying** — `php artisan config:clear` (the installer
  does this automatically).

## Database & files

- **Media URLs 404** — run `php artisan storage:link`.
- **`SQLSTATE` connection errors on a fresh checkout** — make sure the
  database exists and `.env` matches it, or switch to SQLite
  (`DB_CONNECTION=sqlite`) which needs no server.

## Queues & jobs

- **Queue jobs not processed** — make sure a worker is running
  (`composer run dev` or `composer run queue`). Exports and imports will sit
  pending without one.
- **`MaxAttemptsExceededException` on long jobs** — `retry_after` must exceed
  the longest channel timeout (`QUEUE_RETRY_AFTER`, default `1860`, exceeds
  the `heavy` channel's `1800`). If it is lower, a long job is re-reserved by
  another worker while still running and gets rejected.
- **Horizon won't start on Windows** — it is Linux-only
  (`ext-pcntl`/`ext-posix`); use `php artisan queue:work` instead.

## Backups

- **A backup run fails with a `pg_dump`/`mysqldump` error** — the database
  dump tool must be on `PATH` (`pg_dump` for PostgreSQL). A missing binary is
  recorded as a failed run with the underlying message; nothing is corrupted.
- **The archive can't be built** — the ZIP extension (`ext-zip`) must be
  enabled in PHP.
- **The remote copy is skipped** — enable it under **Settings → Remote
  storage** (it must be enabled and fully configured) and turn on **Also send
  backups to remote storage**.
- **Scheduled backups don't run** — the scheduler must be running
  (`php artisan schedule:work` locally); `backup:schedule-tick` evaluates the
  settings every five minutes.

## Frontend

- **Wayfinder route helpers missing for a new route** — regenerate with
  `npm run build` (or run `npm run dev` once).
- **Type errors in generated files** — don't edit
  `resources/js/{actions,routes,wayfinder}/**`; regenerate instead.
- **Locale switch not taking effect** — switching does a full page reload by
  design; a soft Inertia visit won't refresh `<html lang>`.

## Local environment tools

- **EnvKit** — `envkit start` boots nginx, PHP-FPM, PostgreSQL, Redis and
  mailpit; `envkit status` verifies them.
- **Herd / Lerd / Laragon** — ensure the site points at the project's
  `public/` directory and the database service is running; then use
  `php artisan serve` or the tool's `*.test` domain.

## Still stuck?

Search [existing issues](https://github.com/AHS12/evoriq/issues) or open one
using the bug report template.
