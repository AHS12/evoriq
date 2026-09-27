# Configuration

Configuration lives in `.env` (created for you by `composer setup` from
`.env.example`). The most relevant variables:

```dotenv
APP_URL=http://localhost:8000

# Database (pgsql | mysql | mariadb | sqlite)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=evoriq
DB_USERNAME=postgres
DB_PASSWORD=

# Runtime drivers — safe defaults that work without Redis.
# Run `php artisan app:configure-drivers` to switch to Redis when reachable.
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=database

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Drivers & Redis

`.env.example` ships dependency-free defaults (`file`/`file`/`database`) so a
fresh checkout always boots — including the `/setup` wizard — before the
database or Redis is configured. To upgrade to Redis once it is running:

```sh
php artisan app:configure-drivers
```

The command pings Redis and, when reachable, sets `SESSION_DRIVER`,
`CACHE_STORE` and `QUEUE_CONNECTION` to `redis`; otherwise it falls back to
`database`. You can override each driver explicitly:

```sh
php artisan app:configure-drivers --session=redis --cache=redis --queue=redis
```

## Queues

Long-running work (exports and imports) runs through the queue on the
`critical`, `default` and `heavy` channels.

```sh
composer run dev                       # includes a queue listener on all channels
composer run queue                     # or a dedicated worker for all channels
php artisan queue:work --queue=critical,default,heavy   # explicit worker
php artisan schedule:work              # run the scheduler locally
```

Scheduled work (`routes/console.php`): audit log retention pruning,
completed data-processing cleanup, the backup schedule tick, health checks and
Pulse metrics.

**Queue driver:** Redis by default in production setups (`QUEUE_CONNECTION=redis`);
`database` and `file` are selectable. `retry_after` must exceed the longest
channel timeout (default `1860` > the heavy channel's `1800`) — see the
troubleshooting guide if long jobs fail with `MaxAttemptsExceededException`.

**Horizon** monitors queues in production but requires `ext-pcntl`/`ext-posix`
and therefore runs on **Linux only**. On Windows use `php artisan queue:work`.

## Mail

All outgoing mail is queued — never sent synchronously from a request. For
local development, mail is captured by **mailpit** at
<http://127.0.0.1:8025> when using EnvKit; other tools (Herd, Laragon) ship
equivalent mail catchers. Configure SMTP via the standard `MAIL_*` variables
or in-app under Administration → Settings → Mail.

## First-run installer

The `/setup` wizard can write database and driver settings to `.env` for you —
see [getting-started.md](getting-started.md). It refuses to run once setup is
completed; use `php artisan setup:reset` to re-open it in development.

## Clockify integration

Evoriq synchronizes data from [Clockify](https://clockify.me). Clockify IDs are
kept separate from internal IDs and every request goes through the single
rate-limited, paginated HTTP boundary (`app/Services/Clockify/**`); credentials
are stored per-connection and encrypted, never exposed to the frontend.

```dotenv
CLOCKIFY_API_URL=https://api.clockify.me/api/v1
CLOCKIFY_REPORTS_URL=https://reports.api.clockify.me/v1
CLOCKIFY_TIMEOUT=30
CLOCKIFY_RETRY_TIMES=3
CLOCKIFY_RATE_LIMIT_RPS=50
CLOCKIFY_PAGE_SIZE=200
```

## Backups & remote storage

Scheduled backups (files + database) are configured in-app under
**Administration → Settings → Backups**; the schedule, destination and
retention windows are database settings, not `.env`. Backups always land on
the local `backups` disk, and can optionally mirror to an S3/R2 bucket
configured under **Administration → Settings → Remote storage** (written to
`.env` as `AWS_*`). These keys are the config-level fallbacks:

```dotenv
AWS_ENABLED=false
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=

BACKUP_ARCHIVE_PASSWORD=
BACKUP_MAX_AGE_DAYS=1
BACKUP_MAX_MB=5000
BACKUP_KEEP_DAYS=14
BACKUP_EMAIL_MAX_ATTACHMENT_MB=10
BACKUP_EMAIL_TO=
DB_DUMP_TIMEOUT=900
```

See [backups.md](backups.md) for the full guide.

## Settings vs environment

- **Infrastructure** (database, drivers, mail transport, Clockify and remote
  storage credentials) lives in `.env`.
- **Application behavior** (appearance, locale defaults, audit retention,
  backup schedule, feature toggles) lives in global settings editable at
  **Administration → Settings**, backed by `App\Enums\SettingKey`.
- **Per-user preferences** (appearance, language) are stored per user and
  resolved by the locale/appearance middleware.
