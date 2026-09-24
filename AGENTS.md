# AGENTS.md

Guidance for AI agents and humans working in the Evoriq repository. This file is
the **primary authority** for architecture, conventions and workflow. If anything
conflicts with a default framework behavior, this document wins.

See `TDR.md` for the full technical design — it is the source of truth for
product and architecture decisions.

## 1. What this is

Evoriq is a **historical time analytics and reporting** platform built on top of
Clockify. Clockify remains the time-tracking source; Evoriq synchronizes data
into PostgreSQL and provides analytics, reporting, comparisons and exports.

## 2. Stack

- **Backend:** Laravel 13, PHP 8.4, Service–Repository architecture
- **Frontend:** Inertia 3 + React 19 + TypeScript, Tailwind CSS 4, shadcn/ui
- **Validation:** Laravel `FormRequest` (server, source of truth) + Zod (client)
- **Database:** PostgreSQL (SQLite is the fallback / test database)
- **Queue:** Laravel Queue (`database` driver locally)
- **Auth:** Laravel Fortify (registration, reset, email verification, 2FA, passkeys)

## 3. Local environment

Services are provided by **EnvKit** (nginx, PHP-FPM, PostgreSQL, Redis, mailpit).
Start them with `envkit start` and check with `envkit status`.

Local database: `evoriq` on PostgreSQL (`127.0.0.1:5432`, user `postgres`).
Fallback: set `DB_CONNECTION=sqlite` in `.env`.

## 4. Setup and run

```sh
composer setup        # install deps, copy .env, key, configure drivers, migrate --seed, npm install + build
composer run dev      # serve + queue worker + vite (via `php artisan dev`)
```

A **browser installer** is also available at `/setup`. It boots without a
database or Redis, then walks through environment checks, database
configuration (PostgreSQL, MySQL/MariaDB or SQLite), migrations + seeders and
the session/cache/queue drivers before creating the super admin. The same
driver detection runs from the CLI:

```sh
php artisan app:configure-drivers   # enable Redis when reachable, else file/database
```

`.env.example` ships dependency-free driver defaults (`file`/`file`/`database`)
so a fresh checkout always reaches the wizard; the installer upgrades to Redis
when it is running. `composer setup` is the non-interactive equivalent.

Re-run the first-run onboarding locally (development only — clears the install
lock and completion flag and re-opens `/setup`). Refuses to run in production
unless `--force` is passed. The same action is available under Administration →
Settings → Developer → Maintenance when `DEVELOPER_MAINTENANCE_ACTIONS` is on:

```sh
php artisan setup:reset            # keep users; the wizard skips the account step
php artisan setup:reset --fresh    # delete all users; onboarding starts from scratch
```

## 5. Quality gate (required)

Run **`composer check`** before considering any task complete. It runs:

- `npm run check` — frontend format + lint (`vp check`)
- `npm run types:check` — TypeScript compiler
- `composer test` — Pint, PHPStan/Larastan and Pest

Auto-fix formatting and lint issues with `composer check:fix`.

| Purpose                    | Command                     |
| -------------------------- | --------------------------- |
| Full gate (PHP + frontend) | `composer check`            |
| Auto-fix everything        | `composer check:fix`        |
| Format PHP (fix)           | `composer lint`             |
| Check PHP formatting       | `composer lint:check`       |
| PHP static analysis        | `composer types:check`      |
| PHP tests                  | `php artisan test`          |
| PHP gate only              | `composer test`             |
| Frontend format + lint     | `npm run check`             |
| TypeScript type check      | `npm run types:check`       |
| Build frontend assets      | `npm run build`             |
| Security advisories        | `composer security`         |

Git hooks live in `.githooks/`: `pre-commit` runs Pint on dirty files +
`npm run check`; `pre-push` runs the full `composer check`. Install with
`composer hooks`.

## 6. Architecture at a glance

```text
HTTP request
  └─ Controller ──(FormRequest validation)──> Service ──> Repository ──> Eloquent Model ──> PostgreSQL
                                                 │
                                                 ├─ DTO / FilterDTO cross the Service boundary
                                                 └─ Jobs (queue) for long-running sync / exports

Inertia page  <── Controller (Inertia::render) <── Service <── Repository
React (resources/js)  <── typed props (Resources / arrays) <── Controller
```

- **Backend** follows a strict Service–Repository pattern: controllers are thin,
  services own business logic and transactions, repositories own every database
  query. DTOs are the typed currency between the HTTP layer and services.
- **Frontend** is server-driven via Inertia. Pages receive typed props from
  controllers; navigation and forms use Wayfinder route helpers.

## 7. Backend conventions

### 7.1 Directory structure

| Layer              | Path                                                                 | Responsibility                              |
| ------------------ | -------------------------------------------------------------------- | ------------------------------------------- |
| Model              | `app/Models/{Model}.php`                                              | Relations, casts, scopes                    |
| Migration          | `database/migrations/*_create_{table}_table.php`                      | Schema + indexes                            |
| Factory            | `database/factories/{Model}Factory.php`                               | Test data                                   |
| Enum               | `app/Enums/{Name}.php`                                                | Fixed value sets                            |
| DTO                | `app/DTOs/{Module}/{Module}DTO.php`                                   | Typed input for services                    |
| FilterDTO          | `app/DTOs/{Module}/{Module}FilterDTO.php`                             | Typed list filters                          |
| Repository contract| `app/Repositories/Contracts/{Module}RepositoryInterface.php`          | Query contract                              |
| Repository         | `app/Repositories/{Module}/{Module}Repository.php`                    | All Eloquent access                         |
| Service            | `app/Services/{Module}/{Module}Service.php`                           | Business logic + transactions               |
| Request            | `app/Http/Requests/{Module}/{Action}Request.php`                      | Validation                                  |
| Resource           | `app/Http/Resources/{Module}/{Module}Resource.php`                    | Response / prop shaping                     |
| Controller         | `app/Http/Controllers/{Module}/{Module}Controller.php`                | Thin HTTP layer                             |
| Policy             | `app/Policies/{Model}Policy.php`                                      | Authorization                               |
| Job                | `app/Jobs/{Module}/{Job}.php`                                         | Async work                                  |
| Exception          | `app/Exceptions/{Name}.php`                                           | Domain errors                               |

`app/Services/Clockify/**` is **infrastructure**, not a module service: it is the
single HTTP boundary to Clockify (client, paginator, rate limiter). See §7.10.

### 7.2 Controllers (thin)

- Validate with a `FormRequest`, call one service method, return a response.
- **No business logic, no queries, no `Model::where(...)`** in controllers.
- Authorize every action with `Gate::authorize(...)` or a policy.
- Use method injection; prefer the 7 RESTful actions.
- Response styles:
  - **Inertia pages:** `return Inertia::render('reports/index', ['report' => ReportResource::make($report)]);`
  - **JSON endpoints:** return a `Resource` / `Resource::collection(...)` directly.
  - **Redirects:** `return to_route('reports.index');`
- Avoid strict return types on actions that can return multiple response types.

```php
public function store(StoreReportRequest $request, ReportService $service)
{
    Gate::authorize('create', Report::class);

    $report = $service->create(ReportDTO::fromRequest($request));

    return to_route('reports.show', $report);
}
```

### 7.3 Services (business logic)

- Inject **repository interfaces**, never concrete repositories or models.
- Own all `DB::transaction(...)` boundaries for writes.
- Receive DTOs (or primitives), orchestrate repositories, return models /
  paginators / value objects.
- **Never run queries** — that is the repository's job.
- Cross-cutting infra (Clockify, exports) is injected as a dependency.

### 7.4 Repositories (all data access)

- One interface per module in `app/Repositories/Contracts`, one implementation
  in `app/Repositories/{Module}`.
- Bind every interface in `app/Providers/RepositoryServiceProvider.php`.
- `create(array $data)` / `update(Model $model, array $data)` take plain arrays
  (services pass `$dto->toArray()`), not DTOs.
- `findById(int|string $id, array $relations = [])`, `paginate(FilterDTO $filters, int $perPage = 15, array $relations = [])`.
- Implement a reusable `buildFilterQuery(FilterDTO $filters, array $relations = []): Builder`
  and use it from `paginate`/`getAll`.
- Nested relations: expose granular methods (e.g. `deleteItems`, `createItems`)
  and let the service compose them inside a transaction (delete–insert).

### 7.5 DTOs

- `readonly` classes with typed promoted properties.
- `fromRequest(FormRequest $request): self` and `toArray(): array`.
- Filter DTOs come from the index `Request` via `$request->input(...)` (no
  dedicated index request), and include `search`, filterable fields, `orderBy`,
  `orderDirection`.

### 7.6 Models, Enums, Exceptions

- Type every relation (`HasMany`, `BelongsTo`, …) and scope
  (`scopeActive(Builder $query): Builder`).
- Use `Illuminate\Database\Eloquent\Casts\Attribute` for accessors/mutators.
- Use **Enums** for fixed value sets — never magic strings.
- Custom exceptions live in `app/Exceptions/`, extend an SPL exception, and add
  a `render()` only when they map to an HTTP response. Repositories throw domain
  exceptions; services decide retry/error behavior.
- Migrations: add indexes for filterable/foreign columns; include audit columns
  `created_by` / `updated_by` (nullable) where relevant.

### 7.7 Requests & validation

- Use `FormRequest` for anything beyond trivial validation.
- Prefer pipe-separated string rules (`'email' => 'required|string|email'`);
  use arrays only for complex rules (`Rule::enum(...)`).
- Never trust client-supplied ownership IDs — derive the owning user from
  `auth()->id()` in the service.

### 7.8 Resources

- One resource per model; inherit defaults with `...parent::toArray($request)`,
  then append relations with `whenLoaded`.
- Always use `Resource::collection()` / `Resource::make()` — never hand-map
  relations.
- Keep nested resources slim (no timestamps/audit fields unless needed).

### 7.9 Jobs, queue & scheduler

- Jobs are thin: they resolve a service and delegate; no business logic.
- Sync/export jobs must be **idempotent and resumable** (see TDR §23–24).
- All Clockify traffic goes through the rate limiter — jobs never bypass it.
- Schedule recurring work in `routes/console.php` (`Schedule::command(...)`).
- `composer run dev` already runs a queue worker; use `php artisan schedule:work`
  when testing the scheduler.
- **All outgoing mail is queued.** Notifications and mailables implement
  `ShouldQueue`, and they are dispatched **after** the surrounding
  `DB::transaction` commits (never inside it, or the worker can race the write).
  Never send mail synchronously from a request.
- Local dev queue commands: `composer run dev` listens on all channels;
  `composer run queue` (or `php artisan queue:work --queue=critical,default,heavy`)
  works the same. Plain `php artisan queue:work` consumes the `default` queue,
  which is where mail/notifications land.
- **Queue driver:** Redis by default (`QUEUE_CONNECTION=redis`); `database` and
  `file` are selectable. Dispatch to named channels `critical`, `default`,
  `heavy` and resolve their retry/timeout via `App\Registry\QueueRegistry`
  (`QueueName` + `QueueConfigDTO`).
- **`retry_after` must exceed the longest channel timeout** (`QUEUE_RETRY_AFTER`,
  default `1860` > the heavy channel's `1800`). If it is lower, a long job is
  re-reserved by another worker while still running and is rejected with
  `MaxAttemptsExceededException`. Long jobs set `#[FailOnTimeout]` and a
  `failed()` handler so they can never be left "running" forever (see §7.14).
- **Horizon** monitors queues in production, but requires `ext-pcntl`/`ext-posix`
  and therefore **runs on Linux only** (its provider is registered conditionally
  and excluded from auto-discovery). On Windows, run `php artisan queue:work`.

### 7.10 Clockify integration boundary

- **Never** call the Clockify API directly from controllers, models, jobs or
  services. Use `App\Services\Clockify\ClockifyClient` (with
  `ClockifyPaginator` / `ClockifyRateLimiter`), configured via `config/clockify.php`.
- Credentials are per-connection, stored encrypted, and never exposed to the
  frontend. Bind them with `ClockifyClient::forCredentials(...)`.
- Keep Clockify IDs separate from internal IDs; use upsert semantics keyed by the
  Clockify ID.

### 7.11 Single-tenant data access

Evoriq is **single-tenant** — there are no organizations or tenant scopes.
Ownership is tracked with plain foreign keys (e.g. `user_id`); derive the owning
user from `auth()->id()` in the service and never accept it from the client.
Enforce access with policies on every action.

When combining `where` / `orWhere`, group the `orWhere` in a closure so the
conditions bind as intended:

```php
Entry::where(function ($q) {
    $q->where('billable', true)->orWhere('locked', true);
})->get();
```

### 7.12 Testing

- **Pest.** Feature tests use `RefreshDatabase` and the `:memory:` SQLite DB
  configured in `phpunit.xml`.
- Strict 1:1 mapping: **1 Service = 1 unit test**, **1 Controller = 1 feature test**.
- Unit tests mock the repository interface with Mockery and assert the service
  calls it with the right arguments. Files in `tests/Unit` must opt in with
  `uses(Tests\TestCase::class, RefreshDatabase::class);`.
- Feature tests exercise endpoints/pages and assert responses + database state.
- `tests/TestCase.php` seeds the database (`$seed = true`), so RBAC (roles,
  permissions, super admin) and settings exist in every Feature test.
- Shared helpers live in `tests/Pest.php`: `makeUser()`,
  `makeUserWithPermissions()`, `superAdmin()`, `admin()`, `member()`.
- Reusable payloads/fixtures live in `tests/Mock/*` (e.g. `ExportMockData`,
  `UploadMockData`) — use them instead of duplicating arrays.
- Add tests for every new behavior; never leave a module without tests.

### 7.13 RBAC & seeders

- Permissions are declared in `config/permission-registry.php` and read through
  `App\Registry\PermissionRegistry`. Add permissions there, then run
  `php artisan permission:sync` to upsert them and grant them to the super admin.
- Roles are seeded by `database/seeders/RoleSeeder.php`: **Super Admin** (all
  permissions + `Gate::before` bypass), **Admin**, **Member**. System roles have
  `is_system = true` and must not be deleted by the app.
- `php artisan migrate --seed` seeds **RBAC only** (no domain demo data).
  Local super admin: `superadmin@evoriq.test` / `123456` — never seed in production.
- Authorize every action with a policy and `Gate::authorize(...)`.

### 7.14 Exports, imports & the Data Processing Center

- Every long-running operation — **imports, exports and (future) report
  generation** — is tracked with `App\Models\DataProcessingJob` and runs as a
  queued job on the `heavy` channel. The single producer entry point is
  `DataProcessingJobService::dispatch(JobRequest)`, which persists the job and
  hands it to `DataProcessingJobDispatcher` (`ProcessExport` / `ProcessImport`).
  Never run this work synchronously from a request.
- Entities are registered on `App\Enums\DataEntity` (`makeExporter()` /
  `makeImporter()`), which also carries `label`/`icon`/`permissionKey` and the
  import template metadata. Exporter classes live in `app/Exports` and implement
  `App\Exports\Contracts\Exportable` (`collection`, `headings`, `total`,
  `stage`); importers live in `app/Imports` and implement
  `App\Imports\Contracts\Importable`. Files use `maatwebsite/excel` on the disk
  in `config/exports.php`. Exports/imports read from PostgreSQL — **never** query
  Clockify directly (TDR §33).
- The **Data Processing Center UI** lives at `/activity` (nav: **Job activity**)
  (`DataProcessingJob\ActivityController`, `pages/data-processing/index.tsx`) and
  polls live progress; artifacts are also browsable under Files → Generated,
  where uploads and system-generated files are shown together (generated entries
  carry a "System" badge). Module pages add entry points that reuse the same
  dialogs/routes (see the Users page, gated by `user.export` / `user.import`).
- **Permissions** (`DataProcessingJobPolicy`): center access via
  `data-processing.view` / `data-processing.view.all`; cancel/retry/duplicate via
  `data-processing.manage`; delete via `data-processing.delete`. Queueing an
  operation is allowed with the module-level permission (`user.export`,
  `user.import`) **or** the global `export.create` / `import.create`. Completed
  jobs and their files are pruned by the scheduled
  `data-processing:cleanup-completed` command; owners are notified in-app on
  completion/failure. See `docs/DATA_PROCESSING_CENTER_PLAN.md`.
- **Reliability:** export/import jobs set `#[FailOnTimeout]` and use the
  `TracksDataProcessingJob` trait's `failed()` handler, so a row always reaches a
  terminal state (failed) even on timeout; `handle()` never marks failure itself
  (it only updates progress / cancellation). The scheduled
  `data-processing:reap-stale` command (every 5 min) fails rows whose worker was
  lost (crash/OOM). Imports read at `exports.import.chunk_size` (default `100`)
  for frequent progress updates. See `docs/JOB_RELIABILITY_PLAN.md`.

### 7.15 Settings, media & developer tools

- **Settings:** global (single-tenant) settings live in `App\Enums\SettingKey`
  and are stored via `ahs12/laravel-setanjo`. Read/write through the `Settings`
  facade; sync defaults with `php artisan settings:sync` (also handled by
  `SettingSeeder`). Per-user preferences (e.g. appearance) use the package's
  tenant scoping via `$user->settings()` (`HasSettings`) and keys from
  `App\Enums\UserSettingKey` — global settings stay untagged.
- **Media:** files use `spatie/laravel-medialibrary` on the disk configured in
  `config/media-library.php`. Models implement `HasMedia` and register
  collections from `App\Enums\MediaCollection`; `App\Models\Upload` is the
  generic example. Run `php artisan storage:link` so public URLs resolve.
- **Developer tools:** the gated `/developer` page
  (`App\Http\Controllers\Developer\DeveloperController`) requires the
  `developer.view` permission and the `EnsureDeveloperAccess` middleware. It
  links to Telescope, Pulse and Horizon and renders live health checks
  (`App\Checks\*`, registered by `App\Providers\HealthServiceProvider`).
  Telescope is dev-only; Pulse/Horizon are gated by the `viewPulse`/
  `viewHorizon` abilities in `AuthServiceProvider`/`HorizonServiceProvider`.

## 8. Frontend conventions

### 8.1 Directory structure

| Concern        | Path                              |
| -------------- | --------------------------------- |
| Page           | `resources/js/pages/{name}.tsx`   |
| Feature UI     | `resources/js/components/{feature}/` |
| UI primitives  | `resources/js/components/ui/*` (shadcn, do not edit) |
| Layout         | `resources/js/layouts/**`         |
| Hook           | `resources/js/hooks/use-*.ts(x)`  |
| Utility        | `resources/js/lib/*.ts`           |
| Types          | `resources/js/types/*.ts`         |
| Generated      | `resources/js/{actions,routes,wayfinder}/**` (do not edit) |

### 8.2 TypeScript

- Strict mode is on. **No `any`** (use `unknown` + narrowing). Avoid non-null
  assertions (`!`).
- Type component props with a local `type Props = { ... }`.
- Share cross-cutting types in `resources/js/types`.
- `import type { ... }` for type-only imports.

### 8.3 Inertia data & navigation

- Data flows **server → page props**. Type the props the controller sends.
- Use `<Head title="..." />` for titles.
- Navigate with `Link` / `router` and **Wayfinder helpers** — never hard-code URLs:
  ```tsx
  import { dashboard } from '@/routes';
  import { store } from '@/routes/reports';
  ```
- Assign page layout metadata with the static `Page.layout = { breadcrumbs, title }`.

### 8.4 Forms

- Use Inertia's `<Form>` or `useForm`; get action + method from Wayfinder's
  `.form()`.
- Show validation errors with `<InputError message={errors.field} />`; disable
  submit while `processing` and render `<Spinner />`.
- **Validate on the client with Zod** via `useZodForm`
  (`resources/js/hooks/use-zod-form.ts`) and per-form schemas in
  `resources/js/lib/schemas/*`. The matching Laravel `FormRequest` stays the
  server-side source of truth — keep the Zod schema in sync with its rules.
- Disable native browser validation (`noValidate` on the `<form>`) so errors
  render through `<InputError />` instead of browser tooltips.
- **Create/update happens in a `Dialog` opened from the list page** — not a
  dedicated page — unless the form is genuinely large (multi-section wizard).
  Mount the form only while the dialog is open so `useForm` state resets.

### 8.5 Components

- Function components with default export for pages; named exports for shared
  components.
- Keep components presentational; put data fetching in the page/controller.
- Prefer composition over prop explosion. Extract a feature component when a
  page grows beyond one screen of JSX.
- Reuse shadcn primitives (`@/components/ui`) instead of building from scratch.
- The **theme switcher lives in the top-right of the app header**
  (`components/app/theme-toggle.tsx`), not in the settings area.

### 8.6 Styling

- Tailwind utilities only — no inline `style` unless dynamic.
- Merge classes with `cn()` from `@/lib/utils`.
- Variants with `cva` (see existing UI components).
- Always provide dark-mode styles (`dark:`); use semantic tokens
  (`text-muted-foreground`, `border-sidebar-border`).
- Mobile-first; keep spacing consistent with existing pages.

### 8.7 Hooks & state

- Custom hooks live in `resources/js/hooks/use-*.ts(x)` and are named `useX`.
- Prefer local state. Use React context only for cross-cutting concerns
  (theme, sidebar); avoid premature global stores.
- Memoize (`useMemo`/`useCallback`) only when there is a measured need.

### 8.8 Accessibility

- Semantic elements, labels tied to inputs (`htmlFor`), keyboard reachable
  controls, visible focus. shadcn primitives handle most of this — don't fight
  them.

### 8.9 Performance

- Let Inertia code-split pages. Avoid heavy dependencies; check bundle impact.
- Defer/stream expensive server work to jobs; pages should stay fast.

### 8.10 Frontend testing

- No JS test runner is configured yet. If you add one, use `vp test` (Vitest)
  with React Testing Library, and wire it into `composer check`. Until then,
  verify behavior through Inertia feature tests and manual review.

## 9. Generated files — do not edit

- `resources/js/actions/**`, `resources/js/routes/**`, `resources/js/wayfinder/**`
  (Wayfinder — regenerate with `npm run build`)
- `resources/js/components/ui/*` (shadcn/ui — re-publish with `npx shadcn@latest add`)
- `public/build/**`, `vendor/**`, `node_modules/**`

Run `npm run build` after adding routes before referencing new Wayfinder helpers.

## 10. Workflow for a task

1. Read `AGENTS.md` and the relevant `.agents/rules/*` before planning.
2. Plan the module: model/migration/factory → DTO → repository (+ interface) →
   service → request/resource → controller → routes → tests.
3. Implement backend first (service–repository), then the Inertia page/UI.
4. Add tests (unit + feature) alongside the code.
5. Run `composer check` and fix everything before finishing.

## 11. Rules & skills index

- Rules (always enforced): `.agents/rules/backend-architecture.md`,
  `.agents/rules/frontend-architecture.md`
- Skills (procedures):
  - `.agents/skills/generate-module` — scaffold a backend module
  - `.agents/skills/generate-tests` — Pest unit + feature tests
  - `.agents/skills/create-inertia-feature` — build a typed React/Inertia feature
  - `.agents/skills/clockify-sync-feature` — add Clockify sync work safely
  - `.agents/skills/add-permission` — declare, sync and enforce a permission
  - `.agents/skills/add-export` — register a new async export entity
