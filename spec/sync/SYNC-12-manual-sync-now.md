# SYNC-12 — Manual "Sync Now"

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-09
- **Blocks:** SYNC-18
- **TDR:** §15, §16

## 1. Why

Users must be able to force freshness on demand without re-importing everything.
"Sync Now" is a first-class, obvious action that runs an incremental sync on the
shared budget.

## 2. Scope

**In**
- A `SyncNow` action (dashboard card + header/menu) that starts an **incremental**
  run (never a full re-import).
- Budget check first; if insufficient, explain and offer to wait.
- Progress handoff to the pipeline UI.
- Deduplicate: if a sync is already running, open it instead of starting another.

**Out**
- Historical import (PIPE-09/ENT-14); scheduling (SYNC-11).

## 3. Data model
- `SyncRun(trigger=manual, mode=incremental)`.

## 4. Backend
- **Route** `POST /sync` (`sync.store`, `can:sync.manage`) → `SyncNowController`.
- **Service** `SyncNowService::start(connection, workspace)`:
  - if an active run exists → return it (no duplicate);
  - if `!ApiUsageService::canAfford(...)` → return a `budget` result with
    `resets_in` (UI explains);
  - else start an incremental run (SYNC-09, `priority=high`) and return the run
    id for redirect to PIPE-05.
- **Audit:** `sync.manual_started`.

## 5. Frontend / UI
- Dashboard "Clockify Sync" card: last synced, data through, **Sync Now**
  button (loading state), result handling:
  - started → optimistic "Syncing…" + link to the run timeline;
  - already running → "A sync is already running" + link;
  - budget low → "Next sync window in :time" with a disabled/retry state.
- Also available from the pipeline indicator popover (PIPE-08).

### A11y & i18n
- Button disabled while processing; messages translated; `aria-live` for result.

## 6. API / routes / props
- `POST /sync` → `{ status: 'started'|'running'|'budget', run_id?, resets_in? }`.

## 7. Acceptance criteria
- [ ] Clicking Sync Now starts an incremental run (not a full import).
- [ ] A second click while running does not create a duplicate run.
- [ ] When the budget is exhausted, the user sees when the window resets.
- [ ] The action redirects/links to live progress.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncNowServiceTest`: dedupe, budget guard, incremental scope.
- **Feature** `SyncNowFeatureTest`: endpoint authorization, responses for each
  branch, audit row.

## 9. Notes & open questions
- Decide whether Sync Now also nudges reconciliation of the last few days;
  recommendation: no, keep it incremental only.
