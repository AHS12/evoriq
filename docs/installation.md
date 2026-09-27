# Installation

This guide covers installation in depth, including the local environment
tools that take the pain out of running PHP on your machine.

## Requirements

- **PHP 8.3+** with `pdo`, `mbstring`, `openssl`, `tokenizer`, `fileinfo`,
  `curl`, `xml`, `zip` and the PDO driver for your database (`pdo_pgsql`,
  `pdo_mysql` or `pdo_sqlite`)
- **Composer 2**
- **Node.js** `^20.19.0 || >=22.12.0` (Vite 8 requirement)
- A database: **PostgreSQL** (recommended), MySQL/MariaDB, or **SQLite** (no
  server required)
- **Redis** — optional. The app detects it and upgrades drivers automatically;
  without it, file/database drivers are used.

## Local environment tools

You can run the kit with plain PHP + Composer, but these tools give you
managed PHP, a database and a nice URL in one package. **No Docker is
required** — the project ships no Docker support.

| Tool      | Platform       | Notes                                                                 |
| --------- | -------------- | --------------------------------------------------------------------- |
| **Laravel Herd** | macOS / Windows | The official Laravel local dev environment. PHP + nginx, per-site PHP versions, `*.test` domains. |
| **Lerd**  | Linux / macOS  | Free, open-source, Herd-like alternative. Automatic `.test` domains, per-project PHP and Node, one-command HTTPS. |
| **Laragon** | Windows      | Popular all-in-one environment: Apache/nginx, PHP, MySQL/PostgreSQL, Redis, `*.test` domains. |
| **EnvKit** | Cross-platform | Manages project services (nginx, PHP-FPM, PostgreSQL, Redis, mailpit) with simple commands. Used by this repo's own maintainers. |

Pick **one** of the following paths.

### Option A — EnvKit (used by this repo's maintainers)

EnvKit provides nginx, PHP-FPM, PostgreSQL, Redis and mailpit for the project:

```sh
envkit start    # boot the services
envkit status   # verify they are running
```

Then run the app itself:

```sh
composer setup
composer run dev
```

Outgoing mail is captured by **mailpit** at <http://127.0.0.1:8025>.

### Option B — Laravel Herd (macOS / Windows)

1. Install [Herd](https://herd.laravel.com) and use its services panel to
   start PostgreSQL (or MySQL) and optionally Redis.
2. Create the site from the repo folder (Herd gives you a `*.test` domain), or
   just use `php artisan serve` inside the project.
3. Continue with `composer setup` as usual.

### Option C — Lerd (Linux / macOS)

1. Install [Lerd](https://lerd.sh) — it gives you per-project PHP and Node
   versions plus automatic `.test` domains.
2. Point Lerd at the `public/` directory of the project, or use
   `php artisan serve`.
3. Install PostgreSQL (or use SQLite) and continue with `composer setup`.

### Option D — Laragon (Windows)

1. Install [Laragon](https://laragon.org) and enable PostgreSQL and Redis in
   its settings as needed.
2. Place the project in Laragon's `www` directory to get a `*.test` domain, or
   use `php artisan serve`.
3. Continue with `composer setup`.

### Option E — Plain PHP

Any machine with PHP 8.3+, Composer and Node installed works:

```sh
composer setup
composer run dev
```

Use SQLite (`DB_CONNECTION=sqlite`) if you don't want to run a database
server, and start Redis only if you want the upgraded drivers.

## What `composer setup` does

- Installs Composer dependencies
- Copies `.env.example` to `.env` (if missing) and generates the app key
- Configures dependency-free driver defaults (`file`/`file`/`database`) so a
  fresh checkout always boots
- Runs `migrate --seed` (RBAC roles, permissions, settings — no demo data)
- Installs npm dependencies and builds the frontend assets

It is the non-interactive equivalent of the `/setup` browser installer.

## The browser installer

Open **`/setup`** after cloning and serving the app. It boots without a
database or Redis and walks through environment checks, database
configuration (PostgreSQL, MySQL/MariaDB or SQLite), migrations + seeders,
session/cache/queue driver detection and super admin creation.

The same driver detection is available from the CLI:

```sh
php artisan app:configure-drivers   # enable Redis when reachable, else file/database
```

`.env.example` ships dependency-free driver defaults (`file`/`file`/
`database`), and the installer (or the command above) upgrades to Redis when
it is reachable.

## Re-running onboarding (development only)

```sh
php artisan setup:reset            # keep users; the wizard skips the account step
php artisan setup:reset --fresh    # delete all users; onboarding starts from scratch
```

Refuses to run in production unless `--force` is passed. The same action is
available in the app under Administration → Settings → Developer →
Maintenance when `DEVELOPER_MAINTENANCE_ACTIONS` is on.

## Seeded super admin (local/testing only)

```
Email:    superadmin@example.test
Password: 123456
```

Never seed demo data in production — the installer creates its own super
admin.

## Running the app

`composer run dev` starts three processes:

| Process | Command                                                    |
| ------- | ---------------------------------------------------------- |
| server  | `php artisan serve`                                        |
| queue   | `php artisan queue:listen --queue=critical,default,heavy`  |
| vite    | `npm run dev`                                              |

Long-running work (exports/imports) runs on the queue, so keep a worker
alive — it is included in `composer run dev`.

## Production notes

This is a starter kit, not a deploy script. When you deploy your own product
built on it, follow standard Laravel production practice: set `APP_DEBUG=false`,
configure a real queue worker + supervisor, run the scheduler
(`php artisan schedule:work` or cron), use Redis where available, serve over
HTTPS, and run `npm run build`. **Horizon** monitors queues in production but
requires `ext-pcntl`/`ext-posix` and therefore runs on **Linux only**; on
Windows use `php artisan queue:work`.
