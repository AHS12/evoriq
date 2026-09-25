# Plan — Port the audit log + data-loading UX from `larave-react-starter`

**Status:** Done — ported and verified (2026-09-26)
**Source:** `K:\Projects\larave-react-starter` — branch `dev`, commits:
- `12bf37d` — `feat(audit): implement audit logging system with CRUD operations and settings`
- `1bc8e31` — `Enhance data loading UX and pagination controls`

**Target:** `K:\Projects\Evoriq` (branch `main`, HEAD `1ba009f`)
**Related:** `AGENTS.md`, `TDR.md`, `.agents/rules/*`, `.agents/skills/*`,
`docs/NOTIFICATION_SYSTEM_PLAN.md`, `docs/DATA_PROCESSING_CENTER_PLAN.md`

---

## 1. Objective

The two repos share history up to `3825ba7`, then diverged: Evoriq became the
Clockify product, the starter got a cleanup + these two feature commits. We want
the starter's improvements back in Evoriq with the least possible effort:

1. **Audit log** (`spatie/laravel-activitylog` v5): automatic model + auth event
   collection, central redaction, correlation ids, retention pruning, a
   cursor-paginated `/audit-logs` UI, retention settings, and an async
   `audit` export registered on the existing Data Processing Center.
2. **Data-loading UX**: a reusable loading signal in `use-data-table-filters`,
   `DataTable isLoading` wiring, pagination disable-while-fetching, and a
   **per-page selector** across the list pages.

Both are the starter's own work and slot onto an identical base — **the port is
a 2-commit `git cherry-pick` plus 4 tiny conflict resolutions.** No rewriting.

---

## 2. Why this is low effort (validation evidence)

A throwaway clone of Evoriq with the starter added as a remote and both commits
cherry-picked was fully exercised. Result:

| Check | Result |
| --- | --- |
| `git cherry-pick 12bf37d` | Applied; **4 conflicts** (all trivial, see §5) |
| `git cherry-pick 1bc8e31` | Applied **cleanly, zero conflicts** |
| `composer install` (adds `spatie/laravel-activitylog` 5.1.1) | OK |
| `php artisan test` | **368 passed**, 1297 assertions |
| Audit tests only | **41 passed** |
| `composer lint:check` (Pint) | Passed |
| `composer types:check` (PHPStan/Larastan) | **0 errors** |
| `npm run check` (vp format + lint) | Passed (179 files) |
| `npm run build` (Wayfinder + Vite) | OK |
| `npm run types:check` (tsc) | Passed after Wayfinder generation |

The template and Evoriq share the exact same `tests/TestCase.php` and
`phpunit.xml`; `tests/Pest.php` differs only in the seeded super-admin email.
That is why the audits tests run unchanged.

---

## 3. Scope — Port / Skip

### 3.1 Port

| Commit | What | Notes |
| --- | --- | --- |
| `12bf37d` | Audit logging system (backend + Inertia UI + tests + docs + skill) | Core deliverable |
| `1bc8e31` | Data loading UX + pagination controls (incl. per-page selector) | Core deliverable |

### 3.2 Skip (deliberately)

| Commit | Why skipped |
| --- | --- |
| `ad67293` `chore: cleanup for starter template and notification fix` | Starter **removes Clockify** (`ClockifyClient`, `config/clockify.php`, Clockify notification types) and **deletes `TDR.md`** — all of which Evoriq needs. Its notification-form "auto-persist" UX is a separate improvement; revisit later only if wanted. |
| `c2146f6` `feat: add ExportEntity enum` | Superseded by Evoriq's `DataEntity`; the audit commit targets `DataEntity`. |
| `b011680` `enhance CI workflow and testing setup` | Evoriq already has the equivalent (`1ba009f`, `fdac488`, `cb09a44`); `TestCase.php`/`phpunit.xml` are already identical. |
| `5dae9be` `remove outdated taste documentation` | `.commandcode` taste tooling does not exist in Evoriq. |

No other commit after `3825ba7` exists on the starter branch.

---

## 4. What arrives (file inventory)

### 4.1 New backend files (audit)

```
app/ActivityLog/AuditContext.php              # per-request/job correlation id + request context
app/ActivityLog/AuditLogAction.php            # enriches + redacts before write (extends spatie LogActivityAction)
app/Console/Commands/AuditCleanCommand.php    # audit:clean — per-channel retention pruning
app/DTOs/AuditLog/AuditLogFilterDTO.php       # cursor-filter DTO (search, channel, event, causer, dates)
app/Enums/AuditEvent.php                      # created/updated/deleted + login/logout/failed/role_assigned/...
app/Enums/AuditLogName.php                    # auth | security | rbac | settings | domain (+ retentionDays())
app/Exports/AuditLogExport.php                # DataEntity::AUDIT_LOG exporter
app/Http/Controllers/AuditLog/AuditLogController.php   # index/show/prune
app/Http/Middleware/AuditRequestContext.php   # starts AuditContext per HTTP request
app/Http/Resources/AuditLog/AuditLogResource.php
app/Listeners/Audit/AuthAuditSubscriber.php   # login/logout/failed/reset/verified/2FA
app/Models/AuditActivity.php                  # extends spatie Activity
app/Policies/AuditLogPolicy.php               # viewAny/view/prune
app/Repositories/AuditLog/AuditLogRepository.php
app/Repositories/Contracts/AuditLogRepositoryInterface.php
app/Services/Audit/AuditLogService.php        # record() + paginate()/find()/prune()
app/Services/Profile/ProfileService.php       # extracted account-deletion logic (audited)
config/activitylog.php
config/audit.php                              # retention map, pagination, redacted_attributes
database/migrations/2026_09_25_052840_create_activity_log_table.php  # table + 4 composite indexes
routes/audit.php                              # /audit-logs (index/show/prune)
```

### 4.2 New frontend files

```
resources/js/types/audit.ts
resources/js/pages/audit-logs/index.tsx
resources/js/pages/admin/settings/audit.tsx
resources/js/components/app/data-table/data-table-cursor-pagination.tsx
resources/js/components/app/data-table/data-table-per-page.tsx      # from 1bc8e31
```

### 4.3 New tests

```
tests/Feature/AuditLog/AuditLogControllerTest.php
tests/Feature/AuditLog/AuditSettingsTest.php
tests/Feature/AuditLog/AuthEventsTest.php
tests/Feature/AuditLog/AutomaticLoggingTest.php
tests/Feature/Export/AuditLogExportTest.php
tests/Unit/AuditLogServiceUnitTest.php
tests/Unit/AuditRetentionTest.php
```

### 4.4 New docs / agent assets

```
docs/AUDIT_LOG_PLAN.md                          # the starter's own design doc
docs/DATA_LOADING_UX_RESEARCH.md                # from 1bc8e31
.agents/skills/add-audit-logging/SKILL.md
```

### 4.5 Modified existing files (auto-merged, no manual work)

Audit: `app/Enums/DataEntity.php`, `app/Enums/SettingKey.php`,
`app/Http/Controllers/Settings/ProfileController.php`,
`app/Http/Requests/Export/StoreExportRequest.php`, `app/Jobs/ProcessExport.php`,
`app/Jobs/ProcessImport.php`, `app/Models/{Role,Upload,User}.php`,
`app/Providers/{AppServiceProvider,AuthServiceProvider,RepositoryServiceProvider}.php`,
`app/Services/{DataProcessingJob/DataProcessingJobService,Role/RoleService,Setting/NotificationPreferenceService,Setting/SettingService,User/UserService}.php`,
`bootstrap/app.php`, `composer.json`, `composer.lock`,
`config/permission-registry.php`, `database/seeders/RoleSeeder.php`,
`resources/js/components/app-sidebar.tsx`,
`resources/js/components/app/data-table/data-table.tsx`,
`resources/js/layouts/admin/layout.tsx`, `resources/js/types/index.ts`,
`resources/js/types/pagination.ts`, `routes/{admin,console,web}.php`,
`tests/Unit/{DataProcessingJobService,NotificationPreferenceService,RoleService,SettingService,UserService}UnitTest.php`.

UX (`1bc8e31`): `app/DTOs/AuditLog/AuditLogFilterDTO.php`,
`app/DTOs/User/UserFilterDTO.php`,
`resources/js/components/app/data-table/{data-table,data-table-cursor-pagination,data-table-pagination,data-table-per-page}.tsx`,
`resources/js/hooks/{use-appearance,use-data-table-filters}.ts(x)`,
`resources/js/pages/{audit-logs/index,files/index,roles/index,users/index}.tsx`.

---

## 5. The only manual work — 4 conflict resolutions

`git cherry-pick 12bf37d` leaves exactly four conflicted files. Resolutions
(all keep both sides; the starter's side is generic because it stripped Clockify
out):

| File | Resolution |
| --- | --- |
| `app/Providers/AppServiceProvider.php` | Keep the 3 `Clockify\*` imports **and** add `use App\Listeners\Audit\AuthAuditSubscriber;`. |
| `database/seeders/RoleSeeder.php` | Keep `'connection.credentials.update'` **and** add `'audit.manage'` to the Admin `whereNotIn` list. |
| `.agents/rules/backend-architecture.md` | Keep the Clockify HTTP rule **and** append the new audit-trail non-negotiable. |
| `.gitignore` | Take Evoriq's side — **drop** the starter-only `.commandcode` lines. |

Auto-merged without intervention (verified): `app/Enums/SettingKey.php`,
`config/permission-registry.php`, `AGENTS.md`, `composer.json`,
`tests/Unit/RoleServiceUnitTest.php`.

Key auto-merged additions:
- `SettingKey`: `AUDIT_RETENTION_{AUTH,SECURITY,RBAC,SETTINGS,DOMAIN}` (group
  `audit`, `select` type, `retentionOptions()`), `groups()` gains `audit`.
- `config/permission-registry.php`: `audit.view`, `audit.view.all`,
  `audit.manage`, `audit.export`.
- `routes/console.php`: `Schedule::command('audit:clean')->dailyAt('03:30')`.
- `routes/web.php`: `require __DIR__.'/audit.php'`.
- `routes/admin.php`: audit settings `edit`/`update` (group `audit`).
- `AGENTS.md`: new §7.16 "Audit logging", workflow step 5, skills index entry.
- `composer.json`: `spatie/laravel-activitylog: ^5.1`.

---

## 6. Execution steps (exact)

Run from `K:\Projects\Evoriq`:

```sh
# 1. Bring the two commits in (temporary remote; remove it afterwards)
git remote add starter "K:/Projects/larave-react-starter"
git fetch starter dev
git cherry-pick -x 12bf37d      # stop: resolve the 4 conflicts in §5
git cherry-pick -x 1bc8e31      # applies cleanly
git remote remove starter

# 2. Dependencies (composer.lock already carries the package; this verifies)
composer install

# 3. Database + registries
php artisan migrate                 # creates activity_log + composite indexes
php artisan permission:sync         # upserts audit.* permissions
php artisan settings:sync           # seeds audit retention defaults

# 4. Frontend generated helpers
npm run build                       # Wayfinder for audit-logs + admin settings

# 5. Quality gate
composer check
```

`git cherry-pick -x` annotates the source commit hash (nice provenance). If a
cherry-pick of `composer.lock` is ever undesirable, an alternative is to apply
only `composer.json` and run `composer require spatie/laravel-activitylog:^5.1`
to regenerate the lock cleanly — but the lock is byte-identical to the starter's
base here, so the plain cherry-pick already validated.

---

## 7. Feature summary (what you get)

### 7.1 Audit backend

- **Collector:** `spatie/laravel-activitylog` v5, with a custom `AuditActivity`
  model and `activity_model` pointed at it. The app owns the schema migration
  (not the package's), so the table ships with indexes tuned for the real query
  patterns: `(causer_id, created_at)`, `(subject_type, subject_id, created_at)`,
  `(log_name, created_at)`, `(created_at, id)`.
- **Redaction:** `AuditLogAction::transformChanges()` recursively strips keys
  matching `config('audit.redacted_attributes')` (`password*`, `*token`,
  `*secret*`, `card_number`, `ssn`, ...) from both `attribute_changes` and
  `properties`, with a depth guard. Central — a new audited model cannot leak.
- **Correlation:** `AuditContext` (singleton) is started per HTTP request by the
  `AuditRequestContext` middleware, or by jobs via `startForJob()`; every row in
  one request/job shares a `correlation_id` in `properties`.
- **Auth events:** `AuthAuditSubscriber` records login, logout, failed login,
  password reset, email verification and 2FA events on the `auth`/`security`
  channels.
- **Model coverage:** `User` (security), `Role` (rbac), `Upload` (domain) opt in
  with `LogsActivity` + allowlists; `DataProcessingJob` intentionally has no
  trait (progress churn) — only named completed/failed/cancelled events.
- **Named events:** services call `AuditLogService::record()` for transitions
  (role changes, suspensions, setting updates, finished exports/imports).
- **Retention:** per-channel days resolved from `SettingKey::AUDIT_RETENTION_*`
  → config fallback; pruned nightly by `audit:clean`, or on demand via the
  `prune` action gated by `audit.manage`.
- **Export:** `DataEntity::AUDIT_LOG` → `AuditLogExport` on the existing Data
  Processing Center pipeline.

### 7.2 Audit UI

- `/audit-logs` cursor-paginated page with search, channel/event/causer/date
  filters; row detail drawer renders the old→new attribute diff, causer, IP/UA,
  subject and correlation id.
- Scoped access: `audit.view` = own rows only, `audit.view.all` = everything;
  `audit.manage` gates pruning. Sidebar entry under Administration.
- Admin → Settings gains an **Audit log** tab for per-channel retention.

### 7.3 Data-loading UX

- `use-data-table-filters` exposes `isLoading` (tracked via router lifecycle);
  `DataTable` renders its existing dormant skeleton rows during fetch and
  disables pagination while a request is in flight.
- New `data-table-per-page.tsx` selector, wired into the users/roles/files/
  audit-logs pages with partial reloads.

---

## 8. Post-port verification checklist

- [ ] `php artisan migrate` creates `activity_log` with the 4 named indexes.
- [ ] `php artisan permission:sync` reports/creates the 4 `audit.*` permissions.
- [ ] `php artisan settings:sync` shows the 5 `audit_retention_*` settings.
- [ ] Login writes an `auth`/`login` row; failed login writes `login_failed`.
- [ ] Updating a `User`/`Role`/`Upload` writes one dirty-only row; password
      change shows `[REDACTED]`.
- [ ] `/audit-logs` renders, filters, paginates and opens the detail drawer.
- [ ] Admin → Settings → Audit log persists retention; `audit:clean` prunes.
- [ ] List pages show skeleton/disabled pagination while loading; per-page
      selector changes page size.
- [ ] `composer check` green.

---

## 9. Risks & mitigations

| Risk | Mitigation |
| --- | --- |
| Cherry-pick of `composer.lock` conflict | Already validated byte-identical base; fallback is `composer require` to regenerate. |
| `activity_log` table collision with spatie's own migration | Package migration is not auto-loaded; only our migration runs (confirmed by tests on `RefreshDatabase`). |
| Conflicted merge drops Clockify wiring in `AppServiceProvider` | Explicit resolution keeps both sides (§5). |
| Settings writes may bypass Eloquent events | Audit records setting changes explicitly via `AuditLogService`, not model events. |
| New route/permission/UX changes need generated Wayfinder | `npm run build` is part of the steps; TypeScript gate then passes. |
| Starter-only `.commandcode`/taste noise | Explicitly dropped in `.gitignore` conflict. |

---

## 10. Effort estimate

| Step | Estimate |
| --- | --- |
| Cherry-pick + resolve 4 conflicts | 15–30 min |
| `composer install` + `migrate` + syncs | 10 min |
| `npm run build` + `composer check` | 15–20 min |
| Manual UI smoke test | 15–30 min |
| **Total** | **~1 hour** (mostly waiting on tooling) |

---

## 11. Execution order

1. Cherry-pick `12bf37d`; resolve the 4 conflicts (§5).
2. Cherry-pick `1bc8e31` (clean).
3. `composer install`; `php artisan migrate`; `permission:sync`; `settings:sync`.
4. `npm run build`; `composer check`.
5. Smoke-test per §8.

---

## 12. Progress log

- [x] Research + diff both repos; identify the two feature commits and confirm
      the starter-cleanup commit must be skipped.
- [x] Simulate the port in a throwaway clone: cherry-picks apply, 368 tests /
      Pint / PHPStan / vp check / build / tsc all pass.
- [x] Execute on `K:\Projects\Evoriq` — cherry-picks landed as `0c2d840`
      (audit) and `640fbf3` (data loading UX); 4 conflicts resolved as §5.
- [x] `composer install` (adds `spatie/laravel-activitylog` 5.1.1),
      `php artisan migrate` (`activity_log` created), `permission:sync`
      (51 permissions, +4 `audit.*`), `settings:sync` (5 audit retention
      settings), `RoleSeeder` re-run for Admin grants.
- [x] `npm run build` (Wayfinder) + `composer check` — **368 tests / Pint /
      PHPStan(0) / vp check / tsc all green**.
- [x] Smoke checks: 5 `audit*` routes registered, `audit:clean` scheduled
      daily 03:30, `audit.view/view.all/manage/export` permissions present,
      `activity_log` table present.
- [x] Status flipped to "Done".
