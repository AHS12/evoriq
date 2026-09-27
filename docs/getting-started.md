# Getting started

Get Evoriq running in a few commands. For a deeper walkthrough — including
local environment tools like EnvKit, Herd, Laragon and Lerd — see
[installation.md](installation.md).

## Requirements

- PHP 8.3+ with the usual Laravel extensions (`mbstring`, `openssl`,
  `tokenizer`, `fileinfo`, `curl`, `xml`, `zip`) and a PDO driver for your
  database
- Composer 2
- Node.js `^20.19.0 || >=22.12.0`
- A database: PostgreSQL (recommended), MySQL/MariaDB, or SQLite (no server
  required)
- Redis (optional — detected and enabled automatically when running)

## 1. Clone and install

```sh
git clone https://github.com/AHS12/evoriq.git
cd evoriq
composer setup
```

`composer setup` does everything non-interactively: installs Composer and npm
dependencies, creates `.env`, generates the application key, configures safe
driver defaults, migrates + seeds, and builds the frontend assets.

## 2. Start the app

```sh
composer run dev
```

This starts three processes together: the Laravel server, a queue worker and
the Vite dev server.

Then open <http://localhost:8000>.

## 3. Log in

After `composer setup` (or `migrate --seed`) in a local environment:

```
Email:    superadmin@evoriq.test
Password: 123456
```

> Never seed demo data in production — the `/setup` installer creates its own
> super admin.

## Alternative: the browser installer

If you prefer a guided setup, clone the repo, serve it however you like, and
open **`/setup`**. The wizard boots without a database or Redis and walks
through:

1. **Welcome** — environment checks; generates the app key if needed.
2. **Database** — choose PostgreSQL, MySQL/MariaDB or SQLite, test the
   connection, optionally create the database, and save it to `.env`.
3. **Migrate** — runs migrations, seeds roles/permissions/settings and links
   storage.
4. **Drivers** — detects Redis and recommends it for sessions, cache and queue
   (falls back to database/file).
5. **Administrator** — creates the super admin (skipped if one exists).
6. **Finish** — completes setup and signs you in.

Both paths (CLI and installer) converge on the same code — start with either.

## What's inside

Once logged in you get: Fortify authentication (2FA, passkeys), RBAC with
system roles, user & role management, settings, a notification center, media
uploads, an audit trail at `/audit-logs`, a Data Processing Center for async
exports/imports at `/activity`, scheduled backups with an optional S3/R2
destination, developer tools, and 5-language internationalization. The
**Clockify HTTP client** (a single rate-limited, paginated integration
boundary) is in place for the synchronization engine.

Re-run onboarding locally anytime with `php artisan setup:reset`
(`--fresh` also deletes users). Development only.

## Next steps

- [Configuration](configuration.md) — tune database, drivers and mail
- [Architecture](architecture.md) — how the codebase is organized
- [Testing](testing.md) — the quality gate you must pass before contributing
