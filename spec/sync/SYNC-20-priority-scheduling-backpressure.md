# SYNC-20 — Priority, scheduling & backpressure

- **Status:** Draft
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
- [ ] Manual/webhook work always precedes daily, which precedes reconciliation.
- [ ] No job starts if the current window cannot afford it; it defers instead.
- [ ] Deferred work resumes automatically at window reset.
- [ ] Concurrency cap is respected.
- [ ] No code path reaches Clockify without the limiter/budget.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncPriorityTest`: ordering, channel mapping, starvation rules.
- **Feature** `BackpressureTest`: with a 30/h budget, queued jobs defer and
  resume; priority ordering asserted.

## 9. Notes & open questions
- Reconcile the TDR's HIGH/NORMAL/LOW with the project's
  `critical/default/heavy` channels (this spec is the resolution point).
