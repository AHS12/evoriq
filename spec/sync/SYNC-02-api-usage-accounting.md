# SYNC-02 — Plan-aware API usage accounting

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-01, CONN-02
- **Blocks:** SYNC-03, SYNC-04, SYNC-09, SYNC-17, SYNC-20
- **TDR:** §12, §13, §16

## 1. Why

Clockify returns **no `X-RateLimit-*` headers**, so *we* must be the source of
truth for the budget. The limit is drastically different by plan (Free = **30
requests/hour per workspace**, paid = 50/s). The whole "safe pipeline" promise
rests on accurate, plan-aware accounting that every request path shares.

## 2. Scope

**In**
- A self-accounted `clockify_api_usage` window (hourly for Free, per-second for
  paid) using the table from SYNC-01.
- A single accounting service that the rate limiter consults before each request
  and records after.
- A budget guard the planner/orchestrator uses to avoid starting work that
  cannot finish in the current window.
- Events when limits are hit.

**Out**
- The existing `ClockifyRateLimiter`'s in-process pacing (kept as the second
  layer); the usage table is the durable layer.
- The UI (SYNC-17).

## 3. Data model
- `clockify_api_usage` (SYNC-01).

## 4. Backend
- **Service** `Services\Sync\ApiUsageService`:
  - `snapshot(connection, workspace): ApiUsageSnapshot`
    `{ window_type, used, limit, remaining, window_ends_at, resets_in }`.
  - `reserve(connection, workspace, n = 1): bool` — atomically checks + reserves
    within the current window (row lock / conditional upsert).
  - `record(connection, workspace, statusCode?)` — increments used,
    `last_request_at`; records `remaining` from Clockify only if ever provided.
  - `rollover()` — ensures a window exists for `now`.
  - `canAfford(connection, workspace, n): bool` — for the planner.
- **Window resolution:** `hour` when the connection's profile is Free
  (`requests_per_hour`), else `second` (`requests_per_second`). Per-connection
  override (CONN-09) wins.
- **Integration:** `ClockifyClient` calls `ApiUsageService::reserve()` before
  dispatch and `record()` after; if `reserve()` fails, it waits until the window
  resets (Free) or paces (paid) and emits a `rate_limited`/`budget_wait` event.
- **Safety margin:** use `config('clockify.budget_safety_factor')` (default
  0.9) so we never consume the last requests.
- **Events:** on 429 or budget exhaustion emit `WARNING`/`RETRY_SCHEDULED` to
  the PIPE stream (SYNC-14).
- **Audit/metrics:** counters for OPS-01.

## 5. Frontend / UI
- None; snapshot feeds SYNC-17.

### A11y & i18n
- None.

## 6. API / routes / props
- `GET /api/usage` (internal/resource) consumed by SYNC-17.

## 7. Acceptance criteria
- [ ] Free connections consume the **hourly** window; paid use **per-second**.
- [ ] `reserve()` is race-safe under concurrent jobs (no overrun past 90%).
- [ ] When exhausted, the client waits and emits a budget-wait event instead of
      hammering Clockify.
- [ ] `snapshot()` is accurate to the DB and exposes `resets_in`.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ApiUsageServiceTest`: window resolution by plan, reserve/record,
  rollover, safety factor, concurrency via sequential reservations past limit.
- **Feature** `ApiUsageIntegrationTest`: `Http::fake()` bursts respect the budget
  and emit events.

## 9. Notes & open questions
- Free hourly windows make very large imports multi-hour; the orchestrator must
  schedule around window resets (SYNC-09/SYNC-20).
