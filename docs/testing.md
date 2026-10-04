# Testing

The project uses **Pest** with a strict 1:1 mapping: **1 Service = 1 unit
test**, **1 Controller = 1 feature test**.

## Running tests

The suite runs **in parallel** (Pest + Paratest), which is what the git hooks
and CI use:

```sh
php artisan test --parallel             # everything (recommended)
php artisan test --parallel --processes=4   # cap worker processes
php artisan test                        # serial fallback
php artisan test --filter=Setup         # one area
php artisan test --testsuite=Unit       # unit only
```

Tests use `RefreshDatabase` against an in-memory SQLite database (configured
in `phpunit.xml`). The suite seeds RBAC and settings in every feature test via
`tests/TestCase.php` (`$seed = true`).

> **Windows:** `--parallel` is supported (Paratest needs neither `pcntl` nor
> `posix`). Each worker receives its own in-memory SQLite database, so runs are
> isolated. If you see a flake only under `--parallel`, check that the test does
> not write to a shared path — maintenance mode is driven by the `cache` store
> in `phpunit.xml` rather than a shared `storage/framework/maintenance.php`.

## Test helpers

Shared helpers live in `tests/Pest.php`:

- `makeUser()` — a regular user
- `makeUserWithPermissions()` — a user with specific permissions
- `superAdmin()`, `admin()`, `member()` — users with system roles

Reusable payloads/fixtures live in `tests/Mock/*` (e.g. `ExportMockData`,
`UploadMockData`) — use them instead of duplicating arrays.

## Writing tests

- **Unit tests** (`tests/Unit`) mock the repository interface with Mockery and
  assert the service calls it with the right arguments. Files there must opt
  in with `uses(Tests\TestCase::class, RefreshDatabase::class);`
- **Feature tests** (`tests/Feature`) exercise endpoints/pages and assert both
  the response and the database state.
- Add tests for every new behavior; never leave a module without tests.

## The quality gate

Run the full gate before considering any task complete:

```sh
composer check       # frontend format + lint, TypeScript, Pint, PHPStan, Pest
composer check:fix   # auto-fix formatting and lint
```

Individual steps:

| Command | Runs |
| ------- | ---- |
| `composer lint` / `composer lint:check` | Pint (fix / check) |
| `composer types:check` | PHPStan / Larastan |
| `php artisan test --parallel` | Pest (unit + feature) |
| `npm run check` | Frontend format + lint (`vp check`) |
| `npm run types:check` | TypeScript compiler |
| `npm run build` | Production asset build |
| `composer security` | Composer + npm security audits |

## Git hooks

```sh
composer hooks   # installs .githooks
```

- **pre-commit** — Pint on dirty files + `npm run check`
- **pre-push** — the full `composer check`

CI runs the test suite on every pull request — see
[`.github/workflows/tests.yml`](../.github/workflows/tests.yml).
