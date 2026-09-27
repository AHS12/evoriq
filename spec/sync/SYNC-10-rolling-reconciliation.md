# SYNC-10 — Rolling reconciliation

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-09, SYNC-06
- **Blocks:** SYNC-11
- **TDR:** §19, §41

## 1. Why

Daily incremental sync alone is not enough: users edit old entries, restore
deleted ones, and change projects/tasks/tags. A rolling window re-fetches recent
history so our dataset converges on Clockify even when change feeds are
incomplete.

## 2. Scope

**In**
- A scheduled reconciliation job with two cadences: **daily** (today, yesterday,
  previous 7 days) and **weekly** (previous 30–31 days).
- Re-fetch of affected entities over the window and idempotent re-upsert.
- Interaction with the shared budget (lowest priority — runs only on leftover
  budget).

**Out**
- The change feed (SYNC-06), daily incremental (SYNC-11), entity specifics.

## 3. Data model
- Creates `SyncRun(mode=reconciliation)` + jobs (SYNC-01).

## 4. Backend
- **Command** `sync:reconcile {--window=7|31}` scheduled (SYNC-11/console.php).
- **Service** `ReconciliationService::plan(windowDays)`:
  - scope: `TIME_ENTRY` (+ rates/custom field values) for the window, plus
    recently-updated dimensions;
  - build a plan via SYNC-03 with `mode=reconciliation`, `priority=low`.
- **Budget:** reconciliation starts only when the current budget has headroom;
  otherwise it defers to the next window (never competes with manual sync).
- **Idempotency:** re-fetching the same window is safe (SYNC-08).
- **Settings:** enabled flag + weekly window from CONN-09.

## 5. Frontend / UI
- Runs appear in the pipeline UI; `PIPE-10` shows reconciliation frequency/last
  run.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- None; scheduled.

## 7. Acceptance criteria
- [ ] Weekly reconciliation re-fetches the last 30–31 days and updates edits.
- [ ] Deleted/restored entries in the window converge.
- [ ] Reconciliation never runs ahead of manual/initial sync in priority.
- [ ] Disabling it in settings stops scheduling.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ReconciliationServiceTest`: window scope, priority, budget deferral.
- **Feature** `ReconciliationFeatureTest`: edited/deleted entries are corrected
  in the DB after a reconciliation run.

## 9. Notes & open questions
- Windows align to the workspace time zone (ANA-07) to avoid off-by-one at day
  boundaries.
