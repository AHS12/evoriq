# Documentation

Welcome to the **Evoriq** documentation — a historical time analytics and
reporting platform built on top of [Clockify](https://clockify.me). Clockify
remains the time-tracking source of truth; Evoriq synchronizes data into
PostgreSQL and provides analytics, reporting, comparisons and exports.

## Guides

| Guide                                        | What it covers                                                    |
| -------------------------------------------- | ----------------------------------------------------------------- |
| [Getting started](getting-started.md)         | Install, boot the app, first login — the fastest path to running   |
| [Installation](installation.md)               | Full setup details and local environment tools (EnvKit, Herd, Laragon, Lerd) |
| [Configuration](configuration.md)             | `.env` variables, database, drivers & Redis, mail                  |
| [Backups](backups.md)                         | Scheduled backups, remote S3/R2 destinations, retention, restore   |
| [Architecture](architecture.md)               | Service–Repository pattern, project structure, backend/frontend conventions |
| [Testing](testing.md)                         | Pest setup, the quality gate, git hooks                           |
| [Translations](translations.md)               | The 5-locale i18n layer and how to add strings                    |
| [UI conventions](ui-conventions.md)           | Motion tokens, feedback primitives, skeletons, toasts, live regions |
| [Accessibility](accessibility.md)             | Keyboard/focus/contrast audit, skip link, high-contrast tokens    |
| [Troubleshooting](troubleshooting.md)         | Common problems and fixes                                         |

## In the application

- **`/setup`** — the first-run browser installer: environment checks, database
  configuration, migrations + seeding, driver detection and super admin
  creation, all without editing `.env` by hand.
- **`/activity`** — the Data Processing Center: live progress for queued
  exports and imports, with downloadable artifacts.
- **`/audit-logs`** — the audit trail: who did what, when.
- **Settings → Backups** — scheduled backups with a run history, health checks
  and an optional S3/R2 off-site destination (see [backups.md](backups.md)).
- **`/developer`** — developer tools (Telescope, Pulse, Horizon, health
  checks), gated by permission.

## Contributing

See [`AGENTS.md`](../AGENTS.md) for the architecture, conventions and the
required quality gate (`composer check`), and [`TDR.md`](../TDR.md) for the
full technical design. The `.agents/` directory holds the enforced rules and
task skills.
