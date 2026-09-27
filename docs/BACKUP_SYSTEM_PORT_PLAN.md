# Backup System Port Plan (Starter â†’ Evoriq)

**Status:** Done â€” ported and verified (uncommitted)
**Target:** `K:\Projects\Evoriq`
**Source:** `K:\Projects\larave-react-starter` (branch `dev`)
**Source commit:** `96075d7` â€” *feat: add backup management functionality*
**Related commit:** `455dba2` â€” *feat: remove outdated backup system plan documentation*
  (only deletes `docs/BACKUP_SYSTEM_PLAN.md`; that file is **skipped**)
**Merge base with Evoriq:** `3825ba7`

---

## 1. Goal

Port the starter's **backup management system** into Evoriq, adapted to Evoriq's
divergences:

- Evoriq is the Clockify analytics product (not a generic starter); the starter
  removed Clockify in `ad67293`, so providers/configs have drifted apart.
- Evoriq's app dictionaries are now **617 keys per locale** (5 locales, parity
  enforced by `tests/Unit/TranslationParityTest.php`); the starter commit shipped
  its translations against a 460-key file. **Do not** overwrite Evoriq's
  dictionaries â€” merge only the new keys.
- Evoriq keeps its own `welcome.tsx`, `README.md`, `AGENTS.md`, `TDR.md` and
  docs; those are **not** overwritten.
- Evoriq's `RoleSeeder` grants the Admin role every permission except a small
  exclusion list, so the new `backup.*` permissions flow to Admin automatically.

The feature itself is unchanged: `spatie/laravel-backup` with a DB-driven
schedule, a run-history table, S3/R2 off-site mirroring, health monitoring,
email delivery of archives, in-app failure notifications and audit logging.

---

## 2. What the feature is

- **Administration â†’ Settings â†’ Backups** (`backup.view` / `backup.manage`):
  schedule (daily/weekly + time slot), retention, remote-mirror toggle, email
  delivery and the full **run history** (backup / cleanup / monitor) with
  download of finished archives.
- **Administration â†’ Settings â†’ Remote storage** (`settings.view` /
  `settings.update`): S3-compatible credentials written to `.env`
  (`AWS_*`), with a write/read/delete "Test connection" probe.
- **DB-driven schedule:** `backup:schedule-tick` runs every 5 minutes, reads the
  settings and dispatches the work that is due (backup, cleanup, monitor).
  No scheduler restart needed when settings change.
- **`heavy` queue channel:** actual backup runs are queued (`RunBackup`);
  cleanup/monitor run in-process (short).
- **Reliability:** spatie events are correlated to a `BackupRun` row via a
  process-scoped `BackupRunContext`; runs always reach a terminal state.
- **Audit + notifications:** `BACKUP_TRIGGERED/COMPLETED/FAILED`,
  `STORAGE_UPDATED` audit events; in-app `backup.failed` / `backup.unhealthy`
  notifications to `backup.manage` holders.
- **Developer page:** a compact backup status block (next run, last backup,
  health, storage used).

Dependencies on Evoriq code that **already exist** and match:
`App\Helpers\EloquentFilterHelper`, `App\Services\Setup\EnvironmentWriter`,
`App\Registry\QueueRegistry`, `App\Enums\QueueName::HEAVY`,
`App\Services\Audit\AuditLogService::record(...)`,
`App\Services\Notification\NotificationService::create(...)` + notification
DTOs, `App\Enums\{NotificationType,NotificationTargetType,UserRole}`,
`App\Models\Upload`'s `#[Fillable]` attribute style, and the
`Paginated<T>` frontend type.

---

## 3. Dependencies (composer)

| Package | Version (starter) | Why |
| --- | --- | --- |
| `spatie/laravel-backup` | `^10.3` | the backup engine |
| `league/flysystem-aws-s3-v3` | `^3.35` | S3/R2 destination |

Other requirements:

- **`ext-zip`** â€” used by spatie to build the archive. Verify it is enabled in
  the local PHP (`php -m | findstr zip`).
- **`pg_dump`** (PostgreSQL) or `mysqldump` on `PATH` to dump the database.
  Missing binary does **not** corrupt anything â€” the run is recorded as failed
  with the underlying message. (Windows/Laragon dev note.)
- Laravel `^13.17` / PHP `^8.3` in Evoriq â€” compatible with the above.

Install:

```sh
composer require spatie/laravel-backup:^10.3 league/flysystem-aws-s3-v3:^3.35
```

> Do **not** cherry-pick the `composer.json` / `composer.lock` hunks â€” Evoriq's
> lockfile diverged (Clockify deps). Let `composer require` resolve.

---

## 4. File inventory & port strategy

Recommended mechanism (lowest risk, full control):

1. Add the starter as a temporary remote and fetch its objects:
   ```sh
   git remote add starter "K:/Projects/larave-react-starter"
   git fetch starter dev            # makes commit 96075d7 reachable
   ```
2. **Copy the NEW files verbatim** straight from the source commit:
   ```sh
   git checkout 96075d7 -- <new file list in Â§4.1>
   ```
3. **Hand-apply the INTEGRATION edits** in Â§4.2 (guided by
   `git show 96075d7 -- <file>`), because each of those files has diverged.
4. `composer require` (Â§3), then `php artisan permission:sync` and
   `php artisan settings:sync`.
5. Merge translations (Â§6) and port tests (Â§7).
6. Remove the temporary remote when done.

> Alternative: `git cherry-pick -n -x 96075d7` then
> `git restore` the Â§4.3 skip list and the Â§4.2 files, redoing them by hand.
> The `git checkout --` approach avoids most conflict noise, so it is preferred.

### 4.1 NEW files â€” copy verbatim from `96075d7`

Backend:

- `app/Console/Commands/BackupScheduleTickCommand.php`
- `app/DTOs/Backup/BackupRunFilterDTO.php`
- `app/DTOs/Storage/StorageConfigDTO.php`
- `app/Enums/BackupRunStatus.php`
- `app/Enums/BackupRunTrigger.php`
- `app/Enums/BackupRunType.php`
- `app/Http/Controllers/Backup/BackupController.php`
- `app/Http/Controllers/Setting/StorageSettingController.php`
- `app/Http/Requests/Setting/UpdateStorageRequest.php`
- `app/Http/Resources/Backup/BackupRunResource.php`
- `app/Jobs/Backup/RunBackup.php`
- `app/Listeners/Backup/RecordBackupFailure.php`
- `app/Listeners/Backup/RecordBackupSuccess.php`
- `app/Mail/BackupCompletedMail.php`
- `app/Models/BackupRun.php`
- `app/Policies/BackupRunPolicy.php`
- `app/Repositories/Backup/BackupRunRepository.php`
- `app/Repositories/Contracts/BackupRunRepositoryInterface.php`
- `app/Services/Backup/BackupRunContext.php`
- `app/Services/Backup/BackupScheduleService.php`
- `app/Services/Backup/BackupService.php`
- `app/Services/Storage/StorageConfigurator.php`
- `config/backup.php`
- `database/factories/BackupRunFactory.php`
- `database/migrations/2026_09_27_000000_create_backup_runs_table.php`

Frontend:

- `resources/js/components/backup/backup-runs.tsx`
- `resources/js/pages/admin/settings/backup.tsx`
- `resources/js/pages/admin/settings/storage.tsx`
- `resources/js/types/backup.ts`

Tests:

- `tests/Feature/Backup/BackupFeatureTest.php`
- `tests/Feature/Setting/StorageSettingsTest.php`
- `tests/Unit/BackupScheduleServiceUnitTest.php`
- `tests/Unit/StorageConfiguratorUnitTest.php`

Docs:

- The starter's **entire `docs/` guide set** (including the new backup guide).
  See **Â§9** for the file-by-file list and adaptation rules.

### 4.2 INTEGRATION edits â€” hand-merge (files already exist in Evoriq)

| File | Change |
| --- | --- |
| `.env.example` | Add `AWS_ENABLED=false`, `AWS_ENDPOINT=`, `BACKUP_ARCHIVE_PASSWORD`, `BACKUP_MAX_AGE_DAYS=1`, `BACKUP_MAX_MB=5000`, `BACKUP_KEEP_DAYS=14`, `BACKUP_EMAIL_MAX_ATTACHMENT_MB=10`, `BACKUP_EMAIL_TO=`, `DB_DUMP_TIMEOUT=900` (keep the Clockify block intact) |
| `config/database.php` | Add the `pgsql` connection `'dump' => ['timeout' => (int) env('DB_DUMP_TIMEOUT', 900)]` block |
| `config/filesystems.php` | Add the `backups` local disk (`root => storage_path('app/private/backups')`). The `s3` disk already has `endpoint`/`use_path_style_endpoint` â€” no change |
| `config/permission-registry.php` | Append the `backup` module (`backup.view`, `backup.manage`) |
| `app/Enums/AuditEvent.php` | Add `STORAGE_UPDATED`, `BACKUP_TRIGGERED`, `BACKUP_COMPLETED`, `BACKUP_FAILED` cases + `label()` arms |
| `app/Enums/NotificationType.php` | Add `BACKUP_FAILED`, `BACKUP_UNHEALTHY` cases + `label()`, `defaultPriority()` and `icon()` arms |
| `app/Enums/SettingKey.php` | Add the 10 `BACKUP_*` cases and extend `group()`, `label()`, `description()`, `defaultValue()`, `type()`, `options()`, `rules()` (incl. the `Closure` email rule) and `groups()`. Also update the `rules()` docblock return type to include the closure signature. Add a private `backupTimeOptions()` |
| `app/Providers/AppServiceProvider.php` | Add `configureBackup()` (listen for `BackupWasSuccessful` / `BackupHasFailed`) + imports + call it in `boot()` **after** `configureAudit()` |
| `app/Providers/AuthServiceProvider.php` | Map `BackupRun::class => BackupRunPolicy::class` |
| `app/Providers/RepositoryServiceProvider.php` | Bind `BackupRunRepositoryInterface => BackupRunRepository` |
| `app/Http/Controllers/Developer/DeveloperController.php` | Inject `BackupScheduleService` + `BackupRunRepositoryInterface`; add `backupStatus()`; add `'backup' => $this->backupStatus()` to `index()` |
| `app/Http/Controllers/Setting/SettingController.php` | After a successful update, `Artisan::call('queue:restart')` when the group is `general` or `mail` (long-lived workers hold stale config) |
| `routes/admin.php` | Add `storage.*` (view/update/test) and `backup.*` (edit/run/download/update) routes; import `BackupController` + `StorageSettingController` |
| `routes/console.php` | Schedule `backup:schedule-tick` every five minutes, `runInBackground()->withoutOverlapping()` |
| `resources/js/types/index.ts` | `export type * from './backup';` |
| `resources/js/layouts/admin/layout.tsx` | Add **Remote storage** (`CloudUpload`) and **Backups** (`DatabaseBackup`) nav items + route imports (titles are keys â†’ translated by `t(item.title)`) |
| `resources/js/pages/admin/settings/developer.tsx` | Add `backup: BackupDevStatus` prop and two `InfoRow`s (next run, last backup) using `t()` + `appLocale()` (this file was i18n-aligned in Evoriq; the starter hunk is pre-i18n) |
| `resources/js/pages/welcome.tsx` | **Skip** â€” the welcome page will be reworked later; do **not** add the backup feature card or touch its copy |

**Adapt when porting the two new pages:** the starter's `backup.tsx` uses
`<Head title="Backup settings" />` (plain). Evoriq wraps page titles in `t()`
(see the other `admin/settings/*.tsx`), so change it to
`<Head title={t('Backup settings')} />` and add the `Backup settings` key.

### 4.3 SKIP (do not apply)

- `AGENTS.md`, `README.md`, `docs/README.md`, `docs/BACKUP_SYSTEM_PLAN.md`
- `lang/{en,bn,fr,de,es}.json` (publisher files) â€” Evoriq's `__()` labels resolve
  through `lang/app`; mirror there instead (Â§6)
- `composer.json`, `composer.lock` â€” use `composer require` (Â§3)
- The `use Mockery;` removals in ~10 `tests/Unit/*` files â€” unrelated churn

---

## 5. Backend integration details (gotchas)

- **`config/backup.php`** is new: copy verbatim. Note two starter defaults that
  differ from stock spatie: the backup destination disk defaults to `backups`
  (`BACKUP_DISK`), while the monitor defaults to `local`. At runtime
  `BackupScheduleService::runtimeConfig()` overrides **both** to
  `destinationDisks()`, so this is only a fallback mismatch â€” leave as-is.
- **Backup name** is `env('APP_NAME')`. Changing `APP_NAME` later changes the
  backup folder/destination name â€” document it.
- **`SettingKey` additions** feed both the settings UI (`groups()` â†’
  `admin/settings/backup` renders `group.fields`, 10 fields) and the shared
  `SettingController::update` (via `UpdateSettingsRequest`, which validates per
  group from `SettingKey::rules()`). No dedicated backup settings request.
- **`BACKUP_EMAIL_RECIPIENTS`** uses a `Closure` validation rule â€” update the
  `rules()` docblock return type accordingly so PHPStan stays green.
- **Permissions:** after `config/permission-registry.php` changes run
  `php artisan permission:sync` (upserts + grants super admin). Admin receives
  `backup.*` via `RoleSeeder` (not in the exclusion list). Re-seed or re-run the
  RoleSeeder in existing installs.
- **Settings:** run `php artisan settings:sync` (or re-seed) so the 10 new
  `SettingKey` rows exist. `Settings::get` falls back to defaults regardless.
- **Storage credentials** are written to `.env` by `StorageConfigurator`, which
  calls `config:clear` + `queue:restart` and reloads `filesystems.disks.s3`
  in-process. `AWS_SECRET_ACCESS_KEY` is only overwritten when supplied and is
  never returned to the client.
- **Audit redaction:** confirm `AWS_SECRET_ACCESS_KEY` / secret fields stay out
  of the activity log (`config/audit.php` `redacted_attributes`); the storage
  controller logs only `['keys' => 'AWS_*']`.
- **`#[Fillable]` attribute** on `BackupRun` matches Evoriq's model style â€” keep.

---

## 6. Internationalization

The starter added **65** keys to `lang/app/en.json` and **44** to the publisher
`lang/en.json` (many overlapping). Comparing the union against Evoriq's current
617-key dictionaries:

- **7 already present** in Evoriq (`Next`, `Previous`, `Output`, `No output.`,
  `Test connection`, `File`, `Size`) â€” reuse.
- **100 keys are missing** and must be added to **all five** `lang/app/*.json`
  (`en`, `bn`, `fr`, `de`, `es`), translated (Bangla = phonetic transliteration
  of tech terms â€” see `AGENTS.md` Â§8.11).
- Plus **`Backup settings`** (the page `<Head>` title we wrap in `t()`).
- **Drop two dead keys** the starter shipped without a backing `SettingKey`:
  `Backup destination` and `Where backup archives are stored`.

Procedure:

1. Port the JS/PHP changes first (so the literal keys exist).
2. Add the 100 keys to `lang/app/en.json` (English source = key).
3. Translate the 100 keys into `bn`, `fr`, `de`, `es` â€” this can be fanned out
   to parallel agents (4 locales), returning JSON.
4. Merge with the existing tooling
   (`C:\Users\AHS12\AppData\Local\Temp\opencode\merge-i18n.mjs` pattern):
   union of missing keys, abort if any translation is missing.
5. Re-scan the new/edited frontend files for any `t()` literal we missed.
6. `php artisan test tests/Unit/TranslationParityTest.php` must pass (all 5
   files share the exact key set).

### Key appendix (100 keys to add)

Frontend / general (from the commit's `lang/app` additions):

```
:name is a production-ready starter kit: ... scheduled backups, ...   â† replace Evoriq hero copy
:used MB of :max MB
A backup of all files and the database will be queued. It runs in the background.
Access key ID
Amazon S3
Automated file and database backups with a settings-driven schedule, run history, health checks and an optional S3/R2 off-site destination.
Backup
Backup status
Backups
Bucket
Changes take effect on the next scheduler tick.
Cleanup
Cloudflare R2
Configured
Custom S3-compatible
Daily at :time
Destination
Email recipients
Enable remote storage
Endpoint
Every backup, cleanup and health check with its outcome. The list refreshes live while a backup runs.
Files and the database are backed up according to the schedule below.
Health
Health check
Healthy
History
Last backup
Leave blank to keep the stored key
Leave empty for AWS S3
Local (server)
Local (server) + Remote (S3 / R2)
Local (server) or Remote (S3 / R2)
Manual
Next run
No backup yet
No check yet
No runs recorded yet.
Not scheduled
Offer the remote disk as a backup destination.
Page :page of :pages
Provider
Queued
Region
Remote (S3 / R2)
Remote storage
Run backup
Run backup now
Run backup now?
Run history
Running
S3-compatible object storage (AWS S3, Cloudflare R2, ...) used as an off-site backup destination. Credentials are stored in the environment file.
Schedule
Scheduled
Scheduled backups
Secret access key
Storage used
Unhealthy
Unknown
Use a path-style endpoint (required for Cloudflare R2)
Weekly at :time
```

Backend `__()` labels (SettingKey / AuditEvent / NotificationType / services):

```
Enable backups
Backup frequency
Backup time
Keep all backups for (days)
Maximum backup storage (MB)
Backup freshness check (days)
Notify on backup failure
Take scheduled backups of files and the database
How often a scheduled backup runs
Time of day the scheduled backup runs
Days to keep every backup before cleanup keeps only dailies, weeklies and monthlies
Oldest backups are removed once backups use more than this many megabytes
A backup older than this many days is flagged as unhealthy
Send an in-app notification when a backup fails or is unhealthy
Also send backups to remote storage
Keep the local backup and also send a copy to the remote storage when it is configured
Email backup archives
Email the backup archive to backup managers after each successful backup (skipped for very large archives)
Backup failed
Backup unhealthy
Backup triggered
Backup completed
Remote storage updated
Backup health check failed
The backup process failed.
Could not save the remote storage settings: :error
Remote storage settings saved.
Could not write the probe file to the remote disk.
The probe file could not be read back from the remote disk.
Successfully connected to the remote storage bucket.
Connection failed: :error
Backup archive from :app
A new backup of all files and the database completed successfully.
Created at
The backup archive is attached to this email.
The archive is larger than :limit MB and was not attached. Download it from the run history in Settings -> Backups.
Comma-separated email addresses that receive the backup archive. Leave empty to email the backup managers.
Each recipient must be a valid email address.
```

(+ `Backup settings`)

---

## 7. Tests to port

| Test | Notes |
| --- | --- |
| `tests/Feature/Backup/BackupFeatureTest.php` | uses `admin()` / `member()` helpers (present in `tests/Pest.php`); asserts `group.fields` count 10, validation, download, manual run, notifications/audit |
| `tests/Feature/Setting/StorageSettingsTest.php` | storage settings page/update/test |
| `tests/Unit/BackupScheduleServiceUnitTest.php` | Mockery on `BackupRunRepositoryInterface`; helper `configuratorOnUnconfiguredDisk()` |
| `tests/Unit/StorageConfiguratorUnitTest.php` | temp `.env` round-trip; helpers `tempEnvPath()`, `configuratorOn()`, `readEnv()`, `storageConfigDto()` |
| `tests/Feature/Setting/SettingFeatureTest.php` | append the ~24-line backup-settings persistence block from the commit |

Verify no helper-function name collisions across the two unit test files (they
are global in Pest) and that `admin()` has `backup.*` after seeding.

---

## 8. Phased execution checklist

- [x] **Phase 0 â€” prep:** `git remote add starter â€¦`; `git fetch starter dev`;
      confirm `git cat-file -t 96075d7` works.
- [x] **Phase 1 â€” deps:** `composer require spatie/laravel-backup league/flysystem-aws-s3-v3`;
      confirm `php -m` has `zip`.
- [x] **Phase 2 â€” config:** copy `config/backup.php`; edit `config/database.php`,
      `config/filesystems.php`, `.env.example`.
- [x] **Phase 3 â€” domain:** copy migration, factory, `BackupRun`, the three
      `BackupRun*` enums, `BackupRunFilterDTO`, `StorageConfigDTO`,
      repository + interface, services (`BackupService`,
      `BackupScheduleService`, `BackupRunContext`, `StorageConfigurator`),
      job, listeners, mail, command.
- [x] **Phase 4 â€” HTTP:** copy controllers, request, resource, policy; edit
      `routes/admin.php`, `routes/console.php`.
- [x] **Phase 5 â€” wiring:** `AppServiceProvider`, `AuthServiceProvider`,
      `RepositoryServiceProvider`, `DeveloperController`, `SettingController`,
      `AuditEvent`, `NotificationType`, `SettingKey`,
      `config/permission-registry.php`.
- [x] **Phase 6 â€” sync:** `php artisan permission:sync`; `php artisan settings:sync`.
- [x] **Phase 7 â€” frontend:** copy types/pages/component; edit
      `types/index.ts`, `layouts/admin/layout.tsx`, `developer.tsx`;
      wrap `<Head>` in `t()`. **Do not touch `welcome.tsx`.**
- [x] **Phase 8 â€” i18n:** add 100 keys + `Backup settings` to all 5
      `lang/app/*.json`; parity test green.
- [x] **Phase 9 â€” tests:** port the four test files + `SettingFeatureTest`
      addition.
- [x] **Phase 10 â€” docs:** bring the full `docs/` guide set (Â§9); update
      `docs/README.md` and the root `README.md`; optionally port the root
      community files.
- [x] **Phase 11 â€” verify:** `composer check`; `php artisan migrate --seed`;
      manual smoke (Settings â†’ Backups, Settings â†’ Remote storage, run backup).

---

## 9. Documentation port (all starter `docs/` â†’ Evoriq) & README

**Decision:** bring the starter's **entire `docs/` guide set** into Evoriq and
update the README so the guides are discoverable. Evoriq's last commit purged
the old planning docs; this repopulates `docs/` with user-facing guides (the
port plan docs remain alongside them).

Source folder: `K:\Projects\larave-react-starter\docs` (added in `91ca3fb`,
backup guide added in `96075d7`).

### 9.1 Files to bring

| File | Treatment |
| --- | --- |
| `docs/README.md` | Adapt â€” Evoriq doc index (drop badges/"starter kit" wording, keep the guide table; point Contributing at `AGENTS.md`/`TDR.md` since Evoriq has no `CONTRIBUTING.md`) |
| `docs/getting-started.md` | Adapt â€” Evoriq clone/setup path, super admin `superadmin@evoriq.test` / `123456` |
| `docs/installation.md` | Adapt â€” keep the local-tool options; remove starter repo URLs; align with `AGENTS.md` Â§3 (EnvKit, DB `evoriq`) |
| `docs/configuration.md` | Adapt â€” `DB_DATABASE=evoriq`; **add** the Clockify env block and the new `AWS_*` / `BACKUP_*` keys (this plan Â§3) |
| `docs/architecture.md` | Adapt â€” Evoriq architecture: add the Clockify integration boundary (`app/Services/Clockify/**`) and the backup module; keep the Serviceâ€“Repository sections |
| `docs/backups.md` | Port from `96075d7` â€” adapt DB name (`laravel_react_starter` â†’ `evoriq`) and the restore runbook |
| `docs/testing.md` | Port, light adapt â€” Pest/`composer check` already match Evoriq |
| `docs/translations.md` | Port, light adapt â€” matches Evoriq's 5-locale i18n layer |
| `docs/troubleshooting.md` | Adapt â€” remove the starter issues URL; keep the generic fixes |
| `docs/images/screenshot-1.png`, `screenshot-2.png` | Optional â€” starter screenshots; replace with Evoriq screenshots or omit the images and their embeds |

### 9.2 Adaptation rules (every guide)

- Replace "Laravel React Inertia Starter Kit" / "the starter" with **Evoriq**.
- Replace repo/clone URLs and badges with Evoriq's; drop links to files Evoriq
  does not have.
- Use Evoriq specifics: DB `evoriq`, super admin `superadmin@evoriq.test` /
  `123456`, EnvKit, and the Clockify integration.
- Cross-link Evoriq's own docs: `AGENTS.md`, `TDR.md`, `docs/backups.md`.
- References to root community files **not present in Evoriq**
  (`CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`, `LICENSE`) must be
  removed/rewritten â€” **or** those files ported too (optional follow-up; commit
  `91ca3fb` contains `LICENSE`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`,
  `SECURITY.md`, `.github/ISSUE_TEMPLATE/*`, `.github/PULL_REQUEST_TEMPLATE.md`).

### 9.3 README update ("where necessary")

Evoriq's `README.md` is product-specific and longer than the starter's â€” do
**not** overwrite it. Instead:

- Add a **Documentation** section linking `docs/README.md` (one row per guide),
  mirroring the starter README's table.
- Add a **Backups** bullet to Highlights (scheduled backups, run history,
  health checks, optional S3/R2 off-site destination).
- Keep `TDR.md` / `AGENTS.md` as the sources of truth; fix any stale links.
- Leave the "Status" callout intact unless the team wants it updated.

### 9.4 Docs checklist

- [x] Copy/adapt the 9 guides (+ images).
- [x] Update `docs/README.md` index for Evoriq.
- [x] Update root `README.md` (Documentation section + backup highlight).
- [ ] Decide on the optional root community files (LICENSE, CONTRIBUTING, CODE_OF_CONDUCT, SECURITY).

---

## 10. Verification

- `composer check` (Pint, PHPStan/Larastan, Pest, `vp check`, `tsc`).
- `php artisan migrate:fresh --seed` applies the new migration and seeds
  RBAC/settings.
- `php artisan test tests/Unit/TranslationParityTest.php` â†’ 5 locales, equal key
  sets.
- Manual smoke:
  - Settings â†’ Backups page renders with 10 fields and the run history.
  - "Run backup now" queues on `heavy`; a row reaches a terminal state.
  - Settings â†’ Remote storage saves to `.env`, `Test connection` passes against
    a real bucket, secret never returned.
  - Developer page shows the backup status block.
  - Admin nav shows **Remote storage** and **Backups**; Member is forbidden.
- Sanity: `php artisan backup:schedule-tick` runs without errors (dispatch is
  no-op until due).
- Docs: every link in `README.md` and `docs/README.md` resolves; no references
  to starter URLs or missing root community files.

---

## 11. Risks

| Risk | Mitigation |
| --- | --- |
| Windows PHP missing `ext-zip` / `pg_dump` | Recorded as a failed run with the message; document the requirement (Â§3) |
| `spatie/laravel-backup` v10 API drift vs the copied services | Compile/test early; the services use `Config::fromArray`, `BackupLogger`, monitor factories and events |
| Evoriq dictionaries overwritten by the starter's | Merge only the missing keys; parity test guards it (Â§6) |
| `SettingKey` / `AuditEvent` / `NotificationType` hand-merge misses an arm | Mirror each match arm the diff adds; PHPStan + tests catch omissions |
| Permissions not synced on existing installs | `permission:sync` + document that Admin should re-seed |
| Changing `APP_NAME` breaks existing backup folders | Document; keep a stable backup name |
| `queue:restart` added to the shared settings update for `general`/`mail` | Intentional; verify it does not surprise other settings flows |

### Resolved decisions

- **Welcome page:** **skip** the backup feature card â€” the welcome page will be
  reworked later, so its copy is left untouched.
- **Docs:** **port the starter's entire `docs/` guide set** into Evoriq and
  **update the README** where necessary (see Â§9). `docs/backups.md` is included,
  adapted to Evoriq.
- **Optional (undecided):** porting the root community/legal files
  (`LICENSE`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `SECURITY.md`,
  `.github/*` templates) â€” needed only if the docs' links should point at them.

---

## 12. Progress log

- [x] Phase 0 â€” prep
- [x] Phase 1 â€” deps
- [x] Phase 2 â€” config
- [x] Phase 3 â€” domain
- [x] Phase 4 â€” HTTP/routes
- [x] Phase 5 â€” wiring
- [x] Phase 6 â€” permission/settings sync
- [x] Phase 7 â€” frontend
- [x] Phase 8 â€” i18n (5 locales)
- [x] Phase 9 â€” tests
- [x] Phase 10 â€” docs (all starter guides + `README` update)
- [x] Phase 11 â€” verification
