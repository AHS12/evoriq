# SYNC-18 — Sync status & freshness contract

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-09, SYNC-10, SYNC-11
- **Blocks:** DASH-05, PIPE-08
- **TDR:** §15, §35, §36, §38

## 1. Why

The user must always understand data freshness and trust: what is synced, how
current it is, what happened last time, and whether anything is wrong. This
single contract powers the dashboard hero, the indicator, and status messaging.

## 2. Scope

**In**
- One typed `SyncStatusResource`: health, last synced, data through, historical
  range, next automatic sync, active run, budget, last error.
- Backing service + shared prop for the dashboard/indicator.
- Health states: `healthy | delayed | failing | never`.

**Out**
- Progress of a running sync (PIPE-05); budget details (SYNC-17).

## 3. Data model
- Reads connection/workspace freshness fields (`last_synced_at`,
  `last_change_scan_at`, `data_through`, historical bounds), latest run, and
  `ApiUsageService`. No new tables.

## 4. Backend
- **Service** `Services\Sync\SyncStatusService::forOrganization(): SyncStatus`:
  - `last_synced_at` (most recent successful run);
  - `data_through` (max source_at applied);
  - `historical_range` (min/max from synced facts);
  - `next_sync_at` (from the schedule + settings);
  - `active_run` (id, type) if present;
  - `health` = `never` (no run), `failing` (last run failed), `delayed`
    (last success older than 2× schedule), else `healthy`;
  - `last_error` mapped via CONN-07.
- **Resource** `SyncStatusResource`; exposed as a shared prop (PIPE-08) and on
  the dashboard.
- **Caching:** short TTL; invalidated on run finalize.

## 5. Frontend / UI
- `DASH-05` "Clockify Sync" hero: health dot, "Last synced 12m ago", "Data
  through Sep 23, 2026", "Historical: Jan 2021 → Sep 2026", "Next: tomorrow
  01:00", `[Sync Now]` (SYNC-12).
- PIPE-08 freshness slot uses the same resource.
- Failure state mirrors TDR §38: "Sync delayed", reason, "We'll retry
  automatically", `[Retry Now]`.

### A11y & i18n
- Health conveyed by icon+text; all labels translated.

## 6. API / routes / props
- Shared `syncStatus` prop + dashboard prop.

## 7. Acceptance criteria
- [ ] Freshness values reflect the latest successful run and applied changes.
- [ ] Health states switch correctly (never/healthy/delayed/failing).
- [ ] Disabled auto-sync shows "next: manual" instead of a schedule.
- [ ] Failure state exposes the mapped reason and retry.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncStatusServiceTest`: health computation, next_sync, ranges, error
  mapping.
- **Feature** `SyncStatusPropTest`: dashboard/indicator prop shape.

## 9. Notes & open questions
- "Data through" semantics: max `source_at`/updated time of applied entries vs
  scan checkpoint — choose `min(last_synced_at, last change applied)` to avoid
  over-claiming freshness.
