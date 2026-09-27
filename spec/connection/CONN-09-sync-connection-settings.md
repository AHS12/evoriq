# CONN-09 — Sync & connection settings

- **Status:** Draft
- **Epic:** connection
- **Estimate:** M
- **Depends on:** CONN-01, SYNC-11
- **Blocks:** SYNC-10, SYNC-11, SYNC-12, SYNC-20
- **TDR:** §18, §19, §12

## 1. Why

A self-hosted tool must be configurable without code changes: when the daily
sync runs, whether reconciliation is on, the workspace time zone/currency, and
whether to override a misdetected rate limit. These settings drive the pipeline.

## 2. Scope

**In**
- Global settings (via `SettingKey`): auto-sync enabled, daily sync time,
  rolling reconciliation enabled + weekly window, default time zone, default
  currency, max historical years (default 5).
- Per-connection overrides: rate-limit profile override, region, webhook auto-
  registration toggle.
- Settings UI in the admin area; validated FormRequests.

**Out**
- The scheduler itself (SYNC-10/11), budget accounting (SYNC-02).

## 3. Data model
- Add `SettingKey` cases + defaults; sync with `settings:sync` / `SettingSeeder`.
  Per-connection overrides live on `clockify_connections` (CONN-01) columns.

## 4. Backend
- **Enum** `App\Enums\SettingKey` additions:
  `clockify_auto_sync_enabled` (bool), `clockify_daily_sync_time` (time, default
  `01:00`), `clockify_reconciliation_enabled` (bool), `clockify_weekly_window_days`
  (int, default 31), `clockify_timezone` (string), `clockify_currency` (string),
  `clockify_max_history_years` (int, default 5).
- **Service** `Setting\ClockifySettingService` reading/writing via the Settings
  facade; expose a typed `SyncSettings` DTO for consumers.
- **Requests** `UpdateSyncSettingsRequest`, `UpdateConnectionOverridesRequest`.
- **Routes** in `admin.php` (`can:settings.manage`) + a connections section
  (`can:connection.manage`).
- **Audit:** channel `settings`.

## 5. Frontend / UI

**Files**
```text
resources/js/pages/admin/settings/sync.tsx
resources/js/components/setting/clockify-settings-form.tsx
resources/js/components/setting/connection-overrides-form.tsx
resources/js/lib/schemas/sync-settings.ts
```
- General settings card: auto-sync switch, time picker, reconciliation switch +
  weekly window, time zone, currency, max history years (capped at 5).
- Connection overrides card: region, rate-limit override (req/hour or req/sec),
  webhook toggle.

### A11y & i18n
- Uses `SettingField`/`SettingsForm` primitives; translated labels.

## 6. API / routes / props
- `admin.settings.sync` (index/store); `connections.overrides`.

## 7. Acceptance criteria
- [ ] Changing daily sync time reschedules the job (reflects in the schedule).
- [ ] Disabling auto-sync prevents scheduled runs but not manual (SYNC-12).
- [ ] Max history years is enforced by the planner (SYNC-03) and default 5.
- [ ] Per-connection overrides win over detected values.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ClockifySettingServiceTest`: defaults, validation bounds, DTO
  projection.
- **Feature** `SyncSettingsFeatureTest`: save + authorization + schedule reflects
  new time.

## 9. Notes & open questions
- Time zone/currency default to the active workspace when unset; define
  precedence: explicit setting → workspace → app default.
