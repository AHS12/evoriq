# SYNC-20 — Priority, scheduling & backpressure

- **Status:** Done
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-02, SYNC-09
- **Blocks:** SYNC-10, SYNC-11, SYNC-12
- **TDR:** §16, §17

## 1. Why

Manual, scheduled, webhook and reconciliation syncs share one tiny budget.
Without deliberate priority ordering and backpressure they will either starve
each other or overrun Clockify. Priority affects ordering only — it must **never**
bypass the rate limiter.

## 2. Scope

**In**
- A priority model and its mapping to queue channels.
- Budget-aware dispatch: only start work the current window can afford.
- Concurrency caps on the heavy channel.
- Deferral/resume at window resets.
- Fairness rules between triggers (manual/webhook > daily > reconciliation).

**Out**
- Accounting (SYNC-02), orchestration internals (SYNC-09).

## 3. Data model
- `clockify_sync_runs.priority`, `clockify_sync_jobs` (SYNC-01).

## 4. Backend
- **Priority enum** `SyncPriority`: `high` (manual, webhook), `normal` (daily),
  `low` (reconciliation).
- **Channel mapping** via `QueueRegistry`:
  - `high` → `critical` (or `default` for bulk) — short, interactive;
  - `normal` → `default`;
  - `low` → `heavy` (long, interruptible).
  *(Final mapping recorded here; must respect the existing channel config and the
  heavy channel's timeout/retry_after.)*
- **Budget gate:** `SyncRunService::dispatchPending()` uses
  `ApiUsageService::canAfford()`; when the window is exhausted it schedules a
  `sync:resume` at the window reset (no busy-waiting).
- **Concurrency:** `config('clockify.sync_concurrency')` limits in-flight sync
  jobs; the limiter still caps per-second paid traffic.
- **Starvation avoidance:** low-priority reconciliation only runs when no
  high/normal work is pending and budget headroom exists (e.g. > 20% free).
- **Never bypass:** every path funnels through `ClockifyClient` +
  `ApiUsageService`.

## 5. Frontend / UI
- Priority is mostly invisible; `PIPE-10` may show queued work by priority and
  budget pressure.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [x] Manual/webhook work always precedes daily, which precedes reconciliation.
- [x] No job starts if the current window cannot afford it; it defers instead.
- [x] Deferred work resumes automatically at window reset.
- [x] Concurrency cap is respected.
- [x] No code path reaches Clockify without the limiter/budget.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `SyncPriorityTest`: ordering, channel mapping, starvation rules.
- **Feature** `BackpressureTest`: with a 30/h budget, queued jobs defer and
  resume; priority ordering asserted.

## 9. Notes & open questions
- Reconcile the TDR's HIGH/NORMAL/LOW with the project's
  `critical/default/heavy` channels (this spec is the resolution point).

### Implemented notes

- **Channel mapping:** `SyncPriority::queue()` → `high`→`critical`,
  `normal`→`default`, `low`→`heavy`. Workers drain those channels in order, so
  manual/webhook work precedes daily, which precedes reconciliation.
- **Deviation — timeout/retries:** sync jobs keep the **heavy** channel's
  timeout and always run with `tries = 1`, because retries are owned by
  SYNC-13's `SyncRetryPolicy` (a queue attempt count > 1 would double-retry and
  bypass the attempt counter). Priority therefore affects the *channel/ordering*
  only, not the job's retry/timeout budget.
- **Budget gate:** `SyncRunService::dispatchPending()` still refuses to dispatch
  when `ApiUsageService::canAfford()` is false and schedules a resume at the
  window reset; the runner additionally parks a job that hits the limit mid-page
  (SYNC-13).
- **Starvation avoidance:** a `low` run only starts when no `high`/`normal` run
  is active (`hasActiveHigherPriority`) and the window is at least
  `clockify.sync_job.low_priority_min_free_ratio` (20%) free. Otherwise it
  defers to the next window.
- **Concurrency:** `clockify.sync_concurrency` bounds in-flight jobs per wave.
- **Never bypass:** every request flows through `ClockifyClient` →
  `ApiUsageService`; there is no direct HTTP path.
