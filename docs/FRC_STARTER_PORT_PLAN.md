# Plan — Port the best `frc-backend` starter features into Evoriq

**Status:** Final — decisions locked, ready to execute
**Revision 5:** Code generators (Phase 3) **deferred** — Evoriq's value is
Clockify sync + bespoke analytics, not uniform CRUD, so the generator's payoff
is low relative to its build/maintenance cost. `.agents/skills/generate-module`
covers scaffolding consistently in the meantime. Revisit after 2–3 real modules
exist and repetition actually bites. Phase 4 now follows Phase 2.
**Revision 4:** `ahs12/laravel-setanjo` 2.0.0 released and verified Laravel 13
compatible. Decisions from review applied (single-tenant, Redis/Horizon,
exports, developer tools, settings, media). All packages compatibility-checked.
**Source:** `K:\Projects\Herd\frc-backend` (Laravel 12, pure API starter)
**Target:** `K:\Projects\Evoriq` (Laravel 13, Inertia + React full-stack)
**Related:** `TDR.md`, `AGENTS.md`, `.agents/rules/*`, `.agents/skills/*`

---

## 1. Objective

Bring the reusable "starter DNA" of `frc-backend` into Evoriq so that:

1. New backend modules follow the convention-complete Service–Repository recipe
   (DTOs, policies, routes, tests) — scaffolded via the `generate-module` skill
   for now. A `make:crud` generator is **deferred** (see §7 Phase 3).
2. RBAC (roles/permissions) is production-grade and seeded out of the box.
3. `php artisan migrate --seed` produces a usable app (super admin, roles,
   permissions) with no demo domain data.
4. Cross-cutting infrastructure (response/error layer, filtering, queue
   registry, async export tracking, settings, media, developer tools) is in
   place before feature work begins.
5. Agents and humans follow the same patterns via updated `AGENTS.md` +
   `.agents` rules/skills.

Adapted to Evoriq: Inertia/React instead of a pure API, Fortify session auth
instead of Sanctum tokens, and **no tenancy layer at all**.

---

## 2. Locked decisions (from review)

| # | Question | Decision |
| --- | --- | --- |
| 1 | Controller style | **Inertia-first** for pages; JSON endpoints (`respond()`/`fail()`) only for machine concerns (sync status, API usage). The would-be `--style=inertia\|api\|both` generator option is deferred with Phase 3. |
| 2 | Queue backend | **Redis is the default driver**, monitored with **Horizon in production (Linux)**. `database` and `file` remain selectable via env. |
| 3 | Public API / Scribe | **Deferred.** The app is Inertia (no separate REST API). Scribe + Sanctum are added later only if a public/programmatic API is introduced. Base JSON controller helpers still ship for internal endpoints. |
| 4 | Exports | **In scope now** — `maatwebsite/excel` v4 + `DataProcessingJob` tracking. Evoriq is export-heavy. |
| 5 | Observability | **In scope as a "Developer Tools" area** inside the app dashboard (auth + permission gated), not separate tools. Telescope, Pulse, Health, Horizon. |
| 6 | Settings | **Adopt `ahs12/laravel-setanjo` `^2.0`** (Laravel 13 compatible) for global settings + optional per-user preferences. |
| 7 | Media library | **Adopt `spatie/laravel-medialibrary`** for PDFs/images (report files, logos, avatars). |
| 8 | Seed data | **RBAC only** — permissions, roles, super admin, settings defaults. Connections/workspaces are created by the user via the UI. |
| 9 | Super admin | `superadmin@evoriq.test` / `123456` (seeded in local/testing only). |

---

## 3. How Evoriq's full-stack architecture works (context for decisions 1 & 3)

Inertia is **not** a separate REST API:

- A controller returns `Inertia::render('reports/index', $props)`. On the first
  load Inertia returns HTML; on subsequent visits it returns a JSON payload of
  `{ component, props, url }` that the React app renders in place.
- The React page **never calls an API for its data** — data arrives as props.
- Auth is a normal Laravel session (Fortify), shared by server and client.
- JSON endpoints are still useful for things Inertia is not designed for:
  polling (sync progress, API budget), file downloads, and webhooks.

Consequences:

- **Default to Inertia** — pages + typed props, no REST layer.
- **JSON endpoints** are opt-in for machine-facing concerns; those are the only
  routes Scribe would ever document.
- **Scribe/Sanctum are not needed now.** If a public API is added later, we
  install both and document the `api/*` routes without touching the Inertia app.

```text
Browser ──HTTP──> Route ──> Controller ──> Service ──> Repository ──> PostgreSQL
                     │            │
                     │            └─ Inertia::render(page, props)  ← UI data
                     └─ JSON (polling / downloads / webhooks)       ← JSON endpoints
```

---

## 4. Package compatibility (verified against Laravel 13 / PHP 8.4)

| Package | Resolved version | Status |
| --- | --- | --- |
| `spatie/laravel-permission` | `^8.3` | Compatible |
| `maatwebsite/excel` | `^4.0` | Compatible (v4 API, newer than frc's v3) |
| `spatie/laravel-medialibrary` | `^11.23` | Compatible |
| `spatie/laravel-health` | `^1.40` | Compatible |
| `spatie/laravel-backup` | `^10.3` | Compatible |
| `laravel/telescope` (dev) | `^5.25` | Compatible |
| `laravel/pulse` | `^1.8` | Compatible |
| `barryvdh/laravel-ide-helper` (dev) | `^3.7` | Compatible |
| `laravel/horizon` | `^5.50` | **Linux-only** — requires `ext-pcntl` + `ext-posix` (missing on Windows) |
| `ahs12/laravel-setanjo` | `^2.0` | Compatible — `php ^8.4`, `illuminate/contracts ^12||^13` (2.0.0, released 2026-09-23) |

**Horizon constraint:** Redis queues work on Windows, but the Horizon **package
and dashboard cannot run on Windows**. Plan:

- Add `laravel/horizon` to `require`.
- Add it to `extra.laravel.dont-discover` and register its provider **only when
  `extension_loaded('pcntl')`** (i.e. Linux/production).
- `composer setup` installs with `--ignore-platform-req=ext-pcntl
  --ignore-platform-req=ext-posix` (harmless on Linux).
- Windows local dev uses `php artisan queue:work` on Redis and monitors queues
  via Pulse; Horizon UI is available in production.

**Setanjo usage:** version `2.0.0` supports Laravel 12/13 on PHP 8.4. Because
Evoriq is single-tenant, we use **global settings** (`Settings::set/get`) and
optionally **per-user preferences** (`Settings::for($user)`). The package's
"tenant" is any Eloquent model — no organization layer is involved.

---

## 5. Fit analysis — Port / Adapt / Skip

### 5.1 Port with adaptation

| Feature | Adaptation |
| --- | --- |
| `make:crud` + `make:*` generators | **Deferred** — build by hand via `generate-module` until repetition justifies it (§7 Phase 3) |
| RBAC (spatie) | Guard `web` (Fortify), no `tenant_id` on roles; registry pattern retained |
| Permission registry | Evoriq module set (see §8.1) |
| Policies + `Gate::before` super-admin bypass | Retained |
| Seeders | RBAC + super admin + settings defaults only (no domain demo data) |
| `EloquentFilterHelper` | Port as-is |
| `ApiException` + `ApiErrorCode` + base Controller | Port; used by JSON endpoints |
| `QueueRegistry`/`QueueName`/queue channels | Port; Redis default, Horizon in prod, `database`/`file` selectable |
| `DataProcessingJob` + Excel export | Port (core); v4 API |
| Settings (`ahs12/laravel-setanjo`) | Global + optional per-user settings |
| Media library | Port for report files/images |
| Developer tools (Telescope/Pulse/Health/Horizon) | Exposed as an in-app, permission-gated "Developer Tools" area |
| Testing harness | `$seed = true`, Pest auth helpers, `tests/Mock` pattern |

### 5.2 Skip

| Feature | Why |
| --- | --- |
| Tenant + branch scoping, tenant middleware, tenant rules | Single-tenant product |
| Billing / packages / subscriptions / payments / FeatureGuard | Not a SaaS billing product |
| Headless CMS, notifications, appointments, serial blocks, transactions, suppliers, products, UOM | Domain-specific to FRC |
| Sanctum + OTP + multi-device tokens | Fortify session auth; no public API yet |
| Scribe + Scalar/Swagger | Deferred with the public API decision |
| `stevebauman/location`, MaxMind, Scout/Meilisearch | Not needed |
| Husky, Docker, deploy.sh | Evoriq has `.githooks`; deployment out of scope |
| i18n (`laravel-lang/common`) | English-only for now |

---

## 6. Target architecture

### 6.1 Single-tenant

No `OrganizationContext`, `OrganizationAware`, global scopes, or scoping
middleware. Where a record has an owner, it is a plain FK (e.g. `user_id`)
derived from `auth()->id()` in the service — never accepted from the client.
Clockify `workspace_id` is an external identifier, not an isolation boundary.

### 6.2 Auth

Fortify session auth on the `web` guard is the only guard. spatie roles and
policies are seeded/checked on `web`; super-admin bypass via `Gate::before`.

### 6.3 Controller styles

- **Inertia (default):** `Inertia::render` + redirects.
- **API:** base `Controller` `respond()`/`fail()` JSON for machine endpoints.
- **Both:** the two controllers share one service/repository.

### 6.4 Queues

- Driver from `QUEUE_CONNECTION`; **`redis` is the documented default**,
  `database`/`file` supported.
- Queue channels via `QueueRegistry`/`QueueName`: `critical`, `default`, `heavy`.
- Horizon supervises in production; local uses `queue:work`.

### 6.5 Developer Tools (in-app)

A `/developer` Inertia page (permission-gated, e.g. `developer.view`, plus
local-only by default) with cards linking to:

- Telescope (`/telescope`) — debugging
- Pulse (`/pulse`) — metrics
- Health (`/health`) — checks
- Horizon (`/horizon`) — only rendered on Linux/prod
- Backup status — production

Each tool route is wrapped in the app's auth + a `developer` policy. No separate
standalone auth system is ported from frc; it reuses Evoriq's session + RBAC.

---

## 7. Phased implementation plan

### Phase 0 — Foundation decisions (done)

- Decisions locked (§2). All package compatibility verified (§4), including
  `ahs12/laravel-setanjo` 2.0.0. No remaining blockers.

### Phase 1 — RBAC + seeders (2–3 days)

- Install `spatie/laravel-permission` (`^8.3`); publish config + migration;
  default guard `web`.
- Custom `Role`/`Permission` models (`is_system`, `group`, `description`).
- `config/permission-registry.php` + `app/Registry/PermissionRegistry.php` for
  the Evoriq module set (§8.1).
- `app/Policies/*` for User, Role, Connection, Workspace, Report, Export, Settings.
- `AuthServiceProvider`: policy map + `Gate::before` super-admin bypass.
- Seeders: `PermissionSeeder` (upsert), `RoleSeeder` (Super Admin / Admin /
  Member), `UserSeeder` (`superadmin@evoriq.test`), `SettingSeeder`.
- `permission:sync` command.

**Acceptance:** `migrate:fresh --seed` yields super admin + roles + permissions;
`php artisan test` green.

### Phase 2 — Core infrastructure + queues (2 days)

- `app/Helpers/EloquentFilterHelper.php` (port as-is).
- `app/Exceptions/ApiException.php` + `app/Enums/ApiErrorCode.php` + base
  `Controller` helpers.
- `app/Registry/QueueRegistry.php`, `app/Enums/QueueName.php`,
  `app/DTOs/Queue/QueueConfigDTO.php`, queue channels in `config/queue.php`.
- Redis as default `QUEUE_CONNECTION`; install `laravel/horizon`, conditional
  provider registration, `config/horizon.php` supervisors.
- `app/Providers/RepositoryServiceProvider.php` (already present).

**Acceptance:** Redis queue processes a job; Horizon boots on Linux; JSON errors
render RFC 9457; `composer check` green.

### Phase 3 — Code generators (DEFERRED)

**Decision:** skipped for now. Generators pay off against uniform CRUD volume;
Evoriq is dominated by Clockify sync and bespoke, read-heavy analytics modules
where the generated scaffold would be rewritten anyway. The build cost is high
(stubs, type resolution, idempotent auto-wiring of routes/`RepositoryServiceProvider`
bindings, fixing the frc placeholder bugs) and it is a long-lived maintenance
surface every time base classes or conventions shift — especially since the
module shape isn't proven yet.

**Interim:** scaffold modules with the `generate-module` skill, which already
emits the full layered recipe consistently.

**Revisit trigger (rule of three):** build 2–3 real modules by hand first
(starting with the Clockify connection/sync module). If the repetition across
DTO/repository/service/request/resource/controller/routes/tests actually bites,
extract a **minimal** `make:module` that emits the layered skeleton only — skip
sophisticated route/provider auto-editing.

**Deferred scope (if revived):**

- Port `app/Console/Commands/Crud/*` (`FieldType`, `FieldDefinition`,
  `FieldCollector`, `CrudBlueprint`, `ConventionWriter`).
- Rewrite stubs for Evoriq conventions (no tenancy; Inertia + API variants).
- `make:crud {name} {--with-fields} {--fields=} {--style=} {--force}`.
- `make:dto`, `make:service`, `make:repo`, `make:route`, `make:helper` — fix the
  placeholder bugs so standalone commands emit valid code.
- Auto-wiring: route include, repository binding, permission registry, policy map.
- Generator tests (frc has none).

**Acceptance (if revived):** one command produces a working, migrated, tested module.

### Phase 4 — Exports & async tracking (2–3 days)

- `DataProcessingJob` model/service/repository/enums + migration.
- `maatwebsite/excel` v4 exports (CSV/XLSX) + queued export jobs.
- `data-processing:cleanup-completed` command + schedule.

**Acceptance:** a queued export writes a file, tracks progress, is downloadable.

### Phase 5 — Developer Tools, settings, media (2–3 days)

- Telescope + Pulse + `spatie/laravel-health` (+ `DatabaseMigrationCheck`),
  Horizon link, optional backup.
- In-app `/developer` Inertia page + `developer.view` permission + sidebar entry.
- `ahs12/laravel-setanjo` `^2.0` global settings + `SettingKey` enum + `settings:sync`.
- `spatie/laravel-medialibrary` + `MediaCollection`/`UploadType` enums + an
  `Upload` module (service/repository/controller/policy/routes/tests).

**Acceptance:** developer tools reachable and gated; settings persist; media
upload works with conversions.

### Phase 6 — Testing harness & agent docs (1–2 days)

- `tests/TestCase.php` with `$seed = true`; Pest auth helpers.
- `tests/Mock/*` pattern.
- Update `AGENTS.md`, `.agents/rules/*`, `.agents/skills/*` (skill-first module
  workflow, RBAC, queues, developer tools, seeded credentials).

---

## 8. Detailed designs

### 8.1 Permission modules & roles

Modules: `user`, `role`, `connection`, `workspace`, `sync`, `report`, `export`,
`settings`, `developer`. Standard five per module
(`view`, `view.all`, `create`, `update`, `delete`) plus extras:
`sync.trigger`, `sync.reconcile`, `report.export`,
`connection.credentials.update`, `developer.view`.

Roles:
- **Super Admin** (`is_system=true`) — all permissions + `Gate::before` bypass.
- **Admin** (`is_system=true`) — all except destructive role/settings actions.
- **Member** — read + own reports.

Resolve roles by name+guard (avoid frc's hard-coded role PKs).

### 8.2 Generator output (Evoriq) — deferred, retained as reference

This table also documents the **canonical module recipe** the `generate-module`
skill follows by hand today; it becomes the stub contract only if Phase 3 is
revived.

| File | Notes |
| --- | --- |
| migration | fields, indexes, `created_by`/`updated_by` audit columns |
| model | `$fillable`, `$casts`, typed relations, scopes |
| factory | per-type fakes; FK uses target factory |
| DTO + FilterDTO | `readonly`, `fromRequest`/`toArray`, search/filter/order |
| repository + interface | `paginate`/`findById`/`create`/`update`/`delete` + `buildFilterQuery` |
| service | transactions, delegates to repository interface |
| request | pipe rules; plain `unique:`/`exists:` |
| resource | `...parent::toArray()` + slim relations |
| policy | `{key}.view.all`/`view`/`create`/`update`/`delete` + restore/forceDelete |
| controller | Inertia and/or JSON per `--style` |
| routes | `routes/web.php` and/or `routes/api.php` |
| tests | feature + service unit, seeded |

Auto-wiring is idempotent and additive.

### 8.3 Developer Tools gating

- `developer.view` permission seeded for Super Admin/Admin.
- `/developer` page lists tools; individual tool routes wrapped with
  `auth` + a `developer` policy.
- Telescope/Pulse default to local; enable in production only if desired.
- Horizon card hidden when `! extension_loaded('pcntl')`.

---

## 9. Testing strategy

- Keep Evoriq's gate: `composer check` (Pint + Larastan + Pest + vp/tsc).
- `$seed = true` base TestCase provides roles/permissions/super admin.
- Port `tests/Mock/*` pattern; add RBAC allow/deny tests.
- Generator command tests (assert generated files + wiring) apply only if
  Phase 3 is revived.

---

## 10. Agent docs & skills updates

- `AGENTS.md`: skill-first module workflow, RBAC, queue/Redis, developer tools,
  seeded credentials; no tenancy text.
- `.agents/skills/generate-module`: **primary module-scaffolding path** (the
  generator that would have superseded it is deferred). Keep it the single
  source for the module recipe.
- New skills: `add-permission`, `add-export` (optional).

---

## 11. Risks & mitigations

| Risk | Mitigation |
| --- | --- |
| Horizon can't run on Windows | Conditional provider; Redis `queue:work` + Pulse locally; Horizon in prod |
| Excel v4 API differs from frc's v3 | Follow v4 docs; adapt export classes |
| Over-porting | Strict port/skip table; review per phase |
| Convention drift without a generator | `generate-module` skill + `AGENTS.md` are the single source; revisit the generator if drift appears |
| Super-admin credential leakage | Seeded in local/testing only |
| Developer tools exposure | Permission-gated; disabled in production unless enabled |

---

## 12. Effort estimate

| Phase | Estimate |
| --- | --- |
| 0 — Foundation decisions | 0.5 day |
| 1 — RBAC + seeders | 2–3 days |
| 2 — Core infra + queues | 2 days |
| 3 — Code generators | Deferred (saves ~3–4 days) |
| 4 — Exports & async | 2–3 days |
| 5 — Developer tools + settings + media | 2–3 days |
| 6 — Testing harness & docs | 1–2 days |
| **Total** | **~10–14 days** |

---

## 13. Execution order

1. Phase 1 (RBAC + seeders) — done
2. Phase 2 (core infra + queues) — done
3. Phase 4 (exports & async tracking) — done
4. Phase 5 (developer tools + settings + media) — done
5. Phase 6 (testing harness + agent docs) — done

Phase 3 (generators) is deferred and not part of the active sequence.

---

## 14. Progress log

- [x] **Phase 0** — decisions & compatibility
- [x] **Phase 1** — RBAC + seeders
      (`spatie/laravel-permission ^8.3`, custom `Role`/`Permission` models,
      `config/permission-registry.php` + `PermissionRegistry`, `UserPolicy`/
      `RolePolicy`/`PermissionPolicy`, `AuthServiceProvider` with super-admin
      `Gate::before`, `PermissionSeeder`/`RoleSeeder`/`UserSeeder`,
      `permission:sync`, `RbacTest`). Seeded: **39 permissions, 3 system roles,
      super admin `superadmin@evoriq.test` / `123456`**. `composer check` green.
- [x] **Phase 2** — core infra + queues
      (`EloquentFilterHelper`, `ApiException` + `ApiErrorCode` + base `Controller`
      helpers, `QueueRegistry`/`QueueName`/`QueueConfigDTO`, queue channels,
      Redis default queue, `laravel/horizon` wired for Linux with conditional
      provider registration). `composer check` green, 60 tests.
- [~] **Phase 3** — code generators — **deferred / skipped for now** (see §7
      Phase 3; interim path is the `generate-module` skill)
- [x] **Phase 4** — exports & async tracking
      (`DataProcessingJob` model/migration/factory + `DataProcessingJobType`/
      `DataProcessingJobStatus` enums, DTO + FilterDTO, repository + service,
      `maatwebsite/excel ^4.0` exporter (`UserExport`) resolved via
      `ExportEntity`/`ExportFormat`, queued `ProcessExport` job on the `heavy`
      channel, JSON `ExportController` (list/create/show/download/delete) +
      `DataProcessingJobPolicy`, `data-processing:cleanup-completed` scheduled
      daily). `composer check` green, **76 tests**.
- [x] **Phase 5** — developer tools + settings + media
      (`ahs12/laravel-setanjo` global settings + `SettingKey` + `settings:sync`
      + `SettingSeeder`, wired into the export cleanup command;
      `spatie/laravel-health` + `DatabaseMigrationCheck` + `HealthServiceProvider`;
      `laravel/pulse` (+ config/gates) and dev-only `laravel/telescope`; gated
      in-app `/developer` page (Inertia) with `EnsureDeveloperAccess` middleware,
      tool cards and live health results; `spatie/laravel-medialibrary` +
      `MediaCollection`/`UploadType` enums + full `Upload` module
      (model/repo/service/DTO/request/resource/policy/controller + `upload.*`
      permissions) and an Inertia uploads page). `spatie/laravel-backup` was
      left out (the plan marked it optional) and can be added later.
      `composer check` green, **90 tests**.
- [x] **Phase 6** — testing harness & agent docs
      (`tests/Pest.php` helpers `makeUser`/`makeUserWithPermissions`/`superAdmin`/
      `admin`/`member`, `tests/Mock/*` fixtures (`ExportMockData`,
      `UploadMockData`), RBAC allow/deny policy tests, plus `AGENTS.md` §7.12/§11
      and `.agents/rules` + skills refreshed with new `add-permission` and
      `add-export` skills). `composer check` green, **97 tests**.
