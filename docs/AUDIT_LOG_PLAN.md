# Audit Log Module Plan

**Goal:** A production-grade audit trail that answers **who did what, when, and from where** for every security-relevant and business-relevant change in the app. Collection is automatic (Laravel model events + auth events), storage is scalable (selective logging, dirty-only diffs, composite indexes, retention pruning), and browsing happens in a clean admin UI backed by **cursor pagination** (net-new pattern in this codebase, ideal for append-only tables).

---

## 1. Research summary & design decisions

### 1.1 Package choice: `spatie/laravel-activitylog` v5 (recommended)

Evaluated: spatie/laravel-activitylog v5, owen-it/laravel-auditing, fully custom observers, event sourcing.

| Requirement | Winner |
| --- | --- |
| Automatic collection via Laravel features (model events) | spatie v5 — `LogsActivity` trait, zero boilerplate on PHP 8.4 / Laravel 12–13 |
| Industry standard / battle-tested | spatie — 48M+ installs, v5 (Mar 2026) ships `attribute_changes`, `ActivityEvent` enum, swappable action classes |
| Redaction of secrets before write | spatie v5 — custom `LogActivityAction::transformChanges()` |
| Grouping one business transaction | spatie — `LogBatch` batch UUID |
| Retention per event class | spatie — `activitylog:clean {channel} --days=N` |
| Strict Service–Repository read layer + cursor pagination UI | Ours — spatie is only the **writer/collector**; all reads go through our own repository/service/controller/resource |

Rejected alternatives:
- **owen-it/laravel-auditing** — per-model config is nice but smaller ecosystem, no log-name channels, weaker batching story.
- **Fully custom trait/observers** — re-implements what spatie v5 already does well; no ownership benefit.
- **Event sourcing** — massive re-architecture; we need a trail, not a state-rebuilding ledger.

**Core principle from research:** log **domain events, not model churn**. A role change, an account suspension, a settings update → audit. A cron bumping `updated_at` → skip. Logging everything snapshots every attribute on every save and grows the table ~40× faster at scale. We apply three levers: (1) selective `logOnly(...)`, (2) `logOnlyDirty()` (store only the diff, never full snapshots), (3) retention windows per log channel.

### 1.2 Tamper-evidence and PII

- Treat the log's JSON columns as readable by anyone with DB access: **never** record `password`, `remember_token`, `two_factor_secret`, tokens, or full PII values. Enforced centrally in the redaction action (§4.2), not per-model.
- Optional future hardening (Phase 7, not initial scope): hash-chain each row (`hash = sha256(prev_hash + row)`), so edits/deletes break the chain. Both spatie tables and ours are mutable; immutability is a separate concern from auditing.

### 1.3 Scaling levers (built in from day one)

1. **Selective logging** — per-model `logOnly` allowlists; sensitive/noise columns excluded.
2. **Dirty-only diffs** — `logOnlyDirty()` + `dontSubmitEmptyLogs()`; rows shrink ~3.4×.
3. **Named log channels** — `security`, `auth`, `rbac`, `settings`, `domain` … each with its own retention window.
4. **Composite indexes matching real query patterns** — the default spatie stub only indexes morph columns (§3.2).
5. **Cursor pagination** — stable keyset reads on a fast-growing append-only table; no `OFFSET`, no `COUNT(*)` on millions of rows.
6. **Scheduled retention** — per-channel pruning, following the existing `data-processing:cleanup-completed` precedent in `routes/console.php`.
7. **Future (out of scope):** PostgreSQL native table partitioning by month, cold archival to object storage, queueing audit writes off the request cycle (v5's custom `LogActivityAction` supports it — a single insert per event is cheap; only add if profiling demands it).

### 1.4 Correlation

Every audit row carries:
- **Causer** — who (user; `system` for jobs/console with the acting job noted).
- **Subject** — what model/entity it happened to (polymorphic).
- **Batch UUID** — all rows from one HTTP request / business transaction share it, so the UI can show "related events".
- **Request context** — IP address, user agent, URL (merged into properties).

---

## 2. Current state (audit summary)

- **No audit/activity package installed** — composer has spatie enum/image/health/medialibrary/permission/regex/temporary-directory only. No `spatie/laravel-activitylog`, no `owen-it/laravel-auditing`.
- **No observers, no event listeners** — `app/Observers/` doesn't exist; only a closure in `User::booted()` flushing settings. Login/logout/failed-auth events are **not captured anywhere** (Telescope is dev-only).
- **No cursor pagination** — all 17 list endpoints use `->paginate()` (`LengthAwarePaginator`); `resources/js/types/pagination.ts` has page-based `Paginated<T>` only; `data-table-pagination.tsx` assumes `current_page`/`last_page`.
- **Clean module blueprint to follow:** `Upload` module (model → DTO → repository+interface → service → request/resource → controller → policy → routes → tests) and `DataProcessingJob` (FilterDTO with scoping, config-driven perPage).
- **Route namespace note:** `/activity` (`activity.*` routes, `ActivityController`) is **taken** by the Data Processing Center. Audit module must use `audit` / `audit-logs` naming.
- **Permission system:** declare in `config/permission-registry.php` → grant in `RoleSeeder` → `php artisan permission:sync` → enforce via policy + `Gate::authorize`. Super admin bypasses via `Gate::before` — tests must use non-super-admin users.
- **Sidebar:** conditional `can(...)` push in `resources/js/components/app-sidebar.tsx` (Administration group).
- **docs style:** phased checklists with exact file paths (matches `STARTER_KIT_CLEANUP_PLAN.md`).

---

## 3. Storage design

### 3.1 Table

Use spatie v5's `activity_log` table as-is (columns: `log_name`, `event`, `subject_type/subject_id`, `causer_type/causer_id`, `properties` JSON, `attribute_changes` JSON, `batch_uuid`, `created_at`) — no extra columns needed. Request context (IP, user agent, URL) lives in `properties`; diffs live in `attribute_changes`.

One **custom `Activity` model** (`app/Models/AuditActivity.php`, extends spatie's) so we own relations (`causer` typed to `User`), scopes, and future schema tweaks; point `activity_model` config at it.

### 3.2 Composite indexes (migration after the package migration)

```php
Schema::table('activity_log', function (Blueprint $table) {
    // "Who did this?" — causer filter, newest first (cursor keyset order)
    $table->index(['causer_id', 'created_at'], 'idx_audit_causer_time');
    // "What happened to this record?" — subject drill-down
    $table->index(['subject_type', 'subject_id', 'created_at'], 'idx_audit_subject_time');
    // "Channel + time window" — security events, auth events, etc.
    $table->index(['log_name', 'created_at'], 'idx_audit_logname_time');
    // Cursor pagination keyset anchor (global newest-first list)
    $table->index(['created_at', 'id'], 'idx_audit_cursor');
});
```

Rule: equality columns first, time column last — every list filter combination resolves to a seek, never a scan.

---

## 4. Phase 1 — Install & configure the collector

- [ ] `composer require spatie/laravel-activitylog`
- [ ] Publish migrations + config; run package migration.
- [ ] Add `app/Models/AuditActivity.php` (extends spatie `Activity`; typed `causer(): BelongsTo<User>`; `@mixin` docblock per house style) and set `'activity_model'` in `config/activitylog.php`.
- [ ] Create `config/audit.php` — our own config for per-channel retention days + audited-model allowlists (keep spatie's config minimal).
- [ ] Add the composite index migration (§3.2) — naming `*_add_audit_log_indexes_table.php`.
- [ ] Redaction action: `app/ActivityLog/RedactSensitiveFieldsAction.php` extending `LogActivityAction`; `transformChanges()` strips `*.password`, `*.remember_token`, `*.two_factor_secret`, `*token*`, `*secret*` from both `attributes` and `old` sides; register in `config/activitylog.php` `actions.log_activity`.
- [ ] Request context: `app/ActivityLog/EnrichWithContextAction.php` (or merged into the same action) — merges `ip_address`, `user_agent`, `url`, `method` into `properties` when a request is in flight; sets `batch_uuid` per request via `LogBatch` in a terminal middleware `app/Http/Middleware/AuditRequestContext.php`.
- [ ] Enum: `app/Enums/AuditLogName.php` — `SECURITY = 'security'`, `AUTH = 'auth'`, `RBAC = 'rbac'`, `SETTINGS = 'settings'`, `DOMAIN = 'domain'` (+ label/icon metadata in the enum per house pattern).
- [ ] Enum: `app/Enums/AuditEvent.php` — `created`, `updated`, `deleted`, `restored`, plus named domain events (`login`, `logout`, `login_failed`, `password_reset`, `email_verified`, `two_factor_disabled`, `role_assigned`, `permission_granted`, `suspended`, `setting_updated`, `export_completed`, `import_completed`, …).
- [ ] Docs: add `docs/AUDIT_LOG_GUIDE.md` later (Phase 5) describing how to add a model to the audit trail.

## 5. Phase 2 — Audit coverage

### 5.1 Models (add `LogsActivity` + `getActivitylogOptions()`)

| Model | Channel | logOnly | Notes |
| --- | --- | --- | --- |
| `User` | `security` | `name, email, status, is_super_admin` | Never log password/tokens. `setDescriptionForEvent` for readable text. |
| `Role` | `rbac` | `name, is_system` | + `syncPermissions`/attach/detach logged explicitly (§5.3). |
| `Setting` (setanjo) | `settings` | key → value diff | Via its model events or the `Settings` facade wrapper — prefer explicit `AuditLogService` event at the write site so "who changed which setting" always has a causer. |
| `Upload` | `domain` | `name, type, size, media_id` | |
| `DataProcessingJob` | `domain` | `type, status, total_rows` | Status transitions are the interesting events. |

Rule for future models: opt in deliberately, with a `logOnly` allowlist and a channel — never `logAll()`/`logFillable()`.

### 5.2 Auth events (net-new listeners)

`app/Listeners/Audit/*` — registered in `AppServiceProvider::boot()`:

- [ ] `AuditLogin` — `Illuminate\Auth\Events\Login` → `auth` channel, event `login`, properties: IP, user agent, remember flag.
- [ ] `AuditLogout` — `Logout` → event `logout`.
- [ ] `AuditLoginFailed` — `Failed` → event `login_failed`, properties include attempted email (no credentials).
- [ ] `AuditPasswordReset` — `PasswordReset` → `security` channel.
- [ ] `AuditEmailVerified` — `Verified` → `security` channel.
- [ ] 2FA/passkey events from Fortify (`TwoFactorChallenged`, `TwoFactorEnabled/Disabled`, `PasskeyAuthenticated`) → `security` channel.

All listeners log explicitly with `causedBy($user)` — never rely on auto-resolution inside event context.

### 5.3 Explicit service events (the "named event" pattern)

Add a small `app/Services/Audit/AuditLogService.php` (`record(AuditEvent $event, Model $subject, array $properties = [], ?User $actor = null, AuditLogName $channel = DOMAIN)`) and call it from existing services at real transitions:

- [ ] `UserService` — suspend/restore/role changes (`role_assigned`, `suspended`).
- [ ] `RoleService` — permission grants/revocations (`permission_granted`, `permission_revoked`) with batch UUID wrapping the transaction.
- [ ] `DataProcessingJobService` — `markCompleted` / `markFailed` (`export_completed`, `import_failed`, …).
- [ ] Setting writes — `setting_updated` with old/new values (redacted if the key is in the sensitive list).

Jobs/console have no authenticated user: capture the actor **id on the request thread** and pass it explicitly (never rely on `auth()` inside a queued job); use `causedBy($systemActor)` semantics (nullable causer + job label in properties) for unattended work.

## 6. Phase 3 — Read module (Service–Repository)

Follow the `generate-module` checklist exactly:

- [ ] `app/Repositories/Contracts/AuditLogRepositoryInterface.php` — `cursorPaginate(AuditLogFilterDTO $filters): CursorPaginator`, `findById(int $id, array $relations = [])`, `buildFilterQuery(AuditLogFilterDTO $filters): Builder`.
- [ ] `app/Repositories/AuditLog/AuditLogRepository.php` — Eloquent on `AuditActivity`; `EloquentFilterHelper` for search; `ORDERABLE` whitelist (`created_at`, `id`); **always `orderBy('id', 'desc')` with `orderBy('created_at','desc')`** (cursor pagination needs a total order). No transactions (read-only).
- [ ] `app/DTOs/AuditLog/AuditLogFilterDTO.php` — `fromRequest(Request)`: `?int $causerId`, `?AuditLogName $channel`, `?AuditEvent $event`, `?string $subjectType`, `?string $search`, `?string $dateFrom`, `?string $dateTo`, `orderBy = 'created_at'`, `orderDirection = 'desc'`, `perPage = 25` (from `config('audit.pagination', 25)`).
- [ ] Bind in `app/Providers/RepositoryServiceProvider.php` `$bindings`.
- [ ] `AuditLogService` — read-only facade over the repository (list + find); also home of the `record()` writer from §5.3.
- [ ] `app/Http/Resources/AuditLog/AuditLogResource.php` — slim shape: `id`, `channel`/`event` (+ `_label`), `description`, `causer` (`{id, name, email}` when loaded), `subject` (`{type, id, label}` resolved via a small `AuditSubjectLabel` resolver — e.g. user email for User, filename for Upload), `properties` (filtered — strip noise, keep IP/UA), `attribute_changes` (`{old, attributes}` maps for the detail view), `batch_uuid`, `created_at` ISO.
- [ ] `app/Policies/AuditLogPolicy.php` — `viewAny`/`view` = `hasAnyPermission(['audit.view', 'audit.view.all'])`; `prune` = `hasPermissionTo('audit.manage')` (gates the manual prune action — controls who may delete/retire log rows, complementing who may read them).
- [ ] Permissions: add `audit` module to `config/permission-registry.php`:
  - `audit.view` — view audit log entries related to the user's own actions (own-causer rows only; enforced in policy as "view.all OR `causer_id === $user->id`", mirroring the `UploadPolicy` pattern).
  - `audit.view.all` — view all audit log entries (Admin gets this).
  - `audit.manage` — manage the audit log: trigger pruning/cleanup, change retention windows exposed in the UI.
  - Grant `audit.view.all` to Admin in `RoleSeeder`; `audit.manage` stays super-admin-only by default; `audit.view` available for the Member allowlist if product wants self-service history. Run `php artisan permission:sync`.
- [ ] `app/Http/Controllers/AuditLog/AuditLogController.php` — thin: `index()` (`Gate::authorize('viewAny', AuditActivity::class)`, scope rows to the acting user when the user lacks `audit.view.all` — mirror `DataProcessingJobFilterDTO::scopedToUser()`; `Inertia::render('audit-logs/index', ['logs' => ..., 'filters' => $filters])`), `show()` (JSON resource for the detail drawer), `prune()` (`Gate::authorize('prune', AuditActivity::class)` → `AuditLogService::prune(olderThanDays)`; queued if row counts are large). **No strict return types on actions that can return multiple response types** per AGENTS §7.2.
- [ ] `routes/audit.php` — `Route::middleware(['auth','verified'])->prefix('audit-logs')->name('audit-logs.')->group(...)`: `GET /` → `index`, `GET /{auditLog}` → `show`, `POST /prune` → `prune` (name `audit-logs.prune`); require in `routes/web.php`.

## 7. Phase 4 — Frontend (Inertia + React)

### 7.1 Cursor pagination infrastructure (net-new, reusable)

- [ ] `resources/js/types/pagination.ts` — add:
  ```ts
  export type CursorPaginationMeta = {
      per_page: number;
      next_cursor: string | null;
      prev_cursor: string | null;
      has_more: boolean; // computed from next_cursor when absent
  };
  export type CursorPaginated<T> = { data: T[]; meta: CursorPaginationMeta };
  ```
  Re-export via `types/index.ts`.
- [ ] `resources/js/components/app/data-table/data-table-cursor-pagination.tsx` — sibling of `data-table-pagination.tsx`: "Showing first N" style caption + Previous/Next buttons driven by `onPrev()` / `onNext()` (buttons disabled when cursor is null). Do not modify the existing page-based component (shadcn-style primitives stay untouched).
- [ ] `use-data-table-filters.ts` — accept `cursor` as a normal filter param (it already re-visits with arbitrary query params; no structural change expected — verify `page` handling doesn't leak into the URL).

### 7.2 Audit log page

- [ ] `resources/js/types/audit.ts` — `AuditLog`, `AuditLogFilter` types (channel/event as string unions from the enums), re-export from `types/index.ts`.
- [ ] `resources/js/pages/audit-logs/index.tsx` — copy the `users/index.tsx` skeleton:
  - TanStack Table `ColumnDef<AuditLog>`: **When** (relative + tooltip absolute date), **User** (avatar + name), **Channel** (Badge), **Event** (Badge with severity color map: created=success, updated=warning, deleted=destructive, auth/security=default), **Description**, **Subject** (label + type).
  - Toolbar: search box, channel `<Select>`, event `<Select>`, date range, user filter (dropdown via existing user search or a simple `causer_id` select on first load).
  - Row click → detail `Sheet`/`Dialog`: full description, causer with IP/UA, subject link (deep-link to the entity page when resolvable), `attribute_changes` rendered as an old→new **key-value diff** (red values old, green values new, one row per changed attribute), batch UUID with "show related events" filter action.
  - `Page.layout = { breadcrumbs: [{ title: 'Audit Log', href: auditLogsIndex() }], title: 'Audit Log' }`.
  - Gated: only reachable with `audit.view` — hide nav entry via `can('audit.view')`.
- [ ] Sidebar: add **Audit Log** item (lucide `ScrollText`/`History` icon) under the **Administration** group in `resources/js/components/app-sidebar.tsx`.
- [ ] `npm run build` to generate Wayfinder helpers for the new controller, then reference `@/routes/audit-logs` (never hard-code URLs).

## 8. Phase 5 — Retention, docs & developer experience

### 8.1 Retention

- [ ] `config/audit.php`: `retention` map per `AuditLogName` — `auth: 180`, `security: 365`, `rbac: 365`, `settings: 180`, `domain: 90` (days; adjust to compliance needs).
- [ ] Console command `audit:clean` (thin wrapper over `activitylog:clean` per channel, or direct spatie command scheduling): schedule in `routes/console.php` nightly, staggered after the existing `data-processing:cleanup-completed` slot.
- [ ] Manual prune from the UI: "Prune" action on the audit page (Prune dialog with a days input) → `audit-logs.prune` route, gated by `audit.manage` + `ConfirmDialog`; dispatches the same prune logic (queued, on the `heavy` channel, tracked via the Data Processing Center if row counts justify it).
- [ ] Optionally notify super admins of prune summaries in-app (reuse notification infra) — mark as nice-to-have.

### 8.2 Skill for future modules (`.agents/skills/add-audit-logging`)

Create `add-audit-logging/SKILL.md` following the house skill pattern (`add-permission`, `add-export` — frontmatter with `name`/`description`/`argument-hint`), so "audit this module" is a repeatable procedure. The skill documents, step by step:

1. Decide the channel (`AuditLogName`) and the meaningful events for the entity — apply the "would anyone dispute this change?" test; reject churn fields.
2. Add the `LogsActivity` trait + `getActivitylogOptions()` with `logOnly([...])` allowlist, `logOnlyDirty()`, `dontSubmitEmptyLogs()`, `useLogName(...)` — **never** `logAll()`/`logFillable()`.
3. Add named domain events where needed: call `AuditLogService::record(...)` inside the service (inside the transaction, batch-wrapped) — never from controllers/jobs directly.
4. For queued jobs: capture the actor id on the request thread and pass it explicitly.
5. Redaction check: confirm no sensitive columns can enter the diff (central action is a backstop, not an excuse).
6. Extend `app/Models/AuditActivity`-side label resolution (`AuditSubjectLabel`) so the UI can render the subject.
7. Tests: automatic-logging test (create/update/delete → one dirty-only row; `updated_at`-only → no row; redaction assertions) following `tests/Feature/AuditLog/AutomaticLoggingTest.php`.
8. Argument hint: `Entity name, for example: Invoice or Report`.

Register it in the skills index of `AGENTS.md` §11 (see §8.3) so it is discoverable.

### 8.3 AGENTS.md & rules updates

- [ ] `AGENTS.md` — new subsection **§7.16 Audit logging** documenting the conventions: channels enum, `logOnly`/`logOnlyDirty`/`dontSubmitEmptyLogs` rules, central redaction action, `AuditLogService::record()` for named domain events, actor-passing rule for jobs, retention map in `config/audit.php`, and "audit events, not churn" guidance.
- [ ] `AGENTS.md` §11 — add `add-audit-logging` to the skills index (and `audit` to the permission examples if any).
- [ ] `AGENTS.md` §10 (workflow) — add one line: "declare audit coverage for the module (skill: `add-audit-logging`) alongside tests".
- [ ] `.agents/rules/backend-architecture.md` — add the audit boundary rule: services record audit events inside transactions; controllers/jobs never log audit events directly; `logAll()`/`logFillable()` are forbidden.
- [ ] `.agents/rules/frontend-architecture.md` — only if the audit page introduces a reusable pattern worth codifying (cursor pagination component usage) — otherwise skip.

## 9. Phase 6 — Tests (1:1 mapping, Pest)

- [ ] `tests/Unit/Services/AuditLog/AuditLogServiceTest.php` — Mockery on `AuditLogRepositoryInterface`; assert `record()` builds correct channel/event/causer/properties (including explicit actor for job context and null-causer handling).
- [ ] `tests/Feature/AuditLog/AuditLogControllerTest.php` — index renders with cursor meta for permitted users; 403 for members; filters (channel/event/causer/date) change the query; `show` returns the resource with `attribute_changes`; **scoping**: a user with only `audit.view` sees only own-causer rows, `audit.view.all` sees everything; **prune** action forbidden without `audit.manage` and prunes with it.
- [ ] `tests/Feature/AuditLog/AutomaticLoggingTest.php` — create/update/delete an audited model → exactly one row with dirty-only diff; `updated_at`-only save → **no row**; password change → no secret in `attribute_changes` (redaction test).
- [ ] `tests/Feature/AuditLog/AuthEventsTest.php` — `actingAs` login → `auth`/`login` row with IP; failed login attempt row exists.
- [ ] `tests/Feature/Rbac/PoliciesTest.php` — extend with `audit.view` / `audit.view.all` / `audit.manage` cases using `makeUserWithPermissions()` (never super admin — `Gate::before` bypasses).
- [ ] Cursor pagination feature test: seed 30+ rows, walk `next_cursor` to exhaustion, assert no duplicates/gaps and stable ordering.

## 10. Phase 7 — Future / explicitly out of scope

- Hash-chain tamper evidence (`hash`, `prev_hash` columns + writer hook) — implement only if a compliance requirement appears.
- PostgreSQL table partitioning / cold archival — revisit when the table outgrows retention-capped hot storage.
- Queued audit writes via custom `LogActivityAction::save()` override — only if profiling shows insert latency matters.
- Audit log **export** via the existing Data Processing Center (`add-export` skill on `AuditActivity`) — cheap follow-up, deliberately deferred.

---

## 11. Execution order & risk notes

1. Install + config + indexes (§4, §3.2) — schema first so nothing writes unindexed.
2. Enums + redaction action + request-context middleware (§4) — safety rails **before** any model opts in.
3. Model traits + auth listeners + explicit service events (§5) — collection live.
4. Read module: repo → service → resource → policy → permissions → controller → routes (§6).
5. Frontend: cursor types + component → page → sidebar → Wayfinder build (§7).
6. Retention + skill + AGENTS.md/rules updates (§8), tests alongside each phase (§9), `composer check` at every phase boundary.

Risks / gotchas:

- **`activity.*` route/controller names are taken** by the Data Processing Center — use `audit-logs` prefix, `AuditLogController`, `AuditActivity` model throughout.
- **Cursor pagination needs a total order** — always pair `created_at` with `id` in `orderBy`, and mirror that in the composite index (§3.2).
- **Queued jobs have no authenticated user** — capture actor id on the request thread, resolve inside the job; never trust `auth()` off-request (mirrors AGENTS §7.9 mail-after-commit discipline).
- **Super admin bypass** (`Gate::before`) — all policy tests must assert with non-super-admin users.
- **Redaction is a central, not per-model, concern** — one action class so a new audited model can never leak secrets by forgetting to exclude them.
- **`dontSubmitEmptyLogs` + `logOnlyDirty` must be on every model** — a single `logAll()` opt-in reintroduces the 40× growth anti-pattern.
- **Settings package writes may bypass Eloquent events** (setanjo storage) — log settings changes explicitly at the service/facade wrapper instead of relying on model events.

---

## Status

- [x] Phase 1 — Collector install & config
- [x] Phase 2 — Audit coverage (models, auth events, service events)
- [x] Phase 3 — Read module (repository → controller → routes; `audit.view` / `audit.view.all` / `audit.manage` permissions)
- [x] Phase 4 — Frontend (cursor pagination + audit page)
- [x] Phase 5 — Retention, `add-audit-logging` skill, AGENTS.md/rules updates
- [x] Phase 6 — Tests green, `composer check` passing

Implemented 2026-09-25 — `composer check` fully green (353 tests, Pint, PHPStan, tsc, vp check).

Implementation notes (deviations from the original plan, all deliberate):
- v5.1.1 dropped `LogBatch`/`batch_uuid` — correlation uses a request-scoped
  `correlation_id` in `properties` (`App\ActivityLog\AuditContext`, booted by
  the `AuditRequestContext` middleware) instead of a dedicated column.
- Composite indexes were folded into the `create_activity_log_table`
  migration (no separate index-only migration).
- `DataProcessingJob` deliberately has **no** `LogsActivity` trait — its
  `markProgress` churn would flood the trail; only named
  completed/failed/cancelled events are recorded from the service.
