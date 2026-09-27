# SYNC-09 — Sync run orchestration

- **Status:** Draft
- **Epic:** sync
- **Estimate:** L
- **Depends on:** SYNC-03, SYNC-04
- **Blocks:** SYNC-10, SYNC-11, SYNC-12, SYNC-18, ENT-14
- **TDR:** §10, §15, §16, §17, §18

## 1. Why

This is "set up the full import pipeline and schedule it". A single service turns
a plan into a `SyncRun` with ordered jobs, dispatches them within the budget,
tracks completion across jobs, and triggers analytics recalculation — the
conductor of the pulling flow.

## 2. Scope

**In**
- `SyncRunService::start(PlanRequest|ImportPlan, trigger, priority): SyncRun`.
- Create run + jobs from the plan; dispatch jobs respecting budget/priority.
- Aggregate per-job progress into the run; finalize run status.
- **Budget-aware scheduling:** on Free plans, dispatch in waves sized to the
  current hourly window; pause and resume on the next window.
- Hand off to analytics after completion (ANA-03) and notify (PIPE-11).
- Resume an interrupted run (re-dispatch non-final jobs).

**Out**
- Planning math (SYNC-03), job internals (SYNC-04), retry policy (SYNC-13),
  freshness UI (SYNC-18).

## 3. Data model
- `clockify_sync_runs`, `clockify_sync_jobs` (SYNC-01).

## 4. Backend
- **Service** `Services\Sync\SyncRunService`:
  - `start(...)` — persist run (`pending`), create jobs (`pending`), emit
    `STARTED` (SYNC-14), then `dispatchPending()`.
  - `dispatchPending()` — select the next jobs by priority, and while
    `ApiUsageService::canAfford()` dispatch `SyncEntityJob`s (chunked to the
    `heavy` channel). When the window is exhausted, schedule a self-resume at
    window reset (SYNC-20).
  - `onJobFinished(job)` — recalc run counters; when all jobs final, finalize:
    `completed` (or `failed` if none succeeded), `completed_at`, dispatch
    analytics recompute + notification.
  - `resume(run)` — re-dispatch jobs not in a final state (crash recovery).
  - `cancel(run)` — cancel pending jobs; cooperative cancel of running ones.
- **Concurrency:** cap parallel heavy jobs (`config('clockify.sync_concurrency')`)
  so the client queue is not flooded; budget remains the hard limit.
- **Idempotent completion:** a job finishing twice does not double-count.
- **Events:** run/job lifecycle → PIPE stream (`run_type=sync`).
- **Audit:** `sync.run_started|completed|failed|cancelled`.

## 5. Frontend / UI
- Run progress is rendered by PIPE-05 (sync runs reuse the pipeline UI). The
  `SyncRun` maps to a `PipelineRunResource` with `run_type=sync`.
- Manual trigger from the dashboard/`Sync Now` (SYNC-12).

### A11y & i18n
- Run/job/phase labels translated.

## 6. API / routes / props
- Internal; surfaced by PIPE + SYNC-12/18.

## 7. Acceptance criteria
- [ ] Starting a plan creates one run with the planned jobs and dispatches them
      within the budget.
- [ ] On Free plans the run pauses at the window edge and resumes automatically
      at reset without user action.
- [ ] A run interrupted by a worker crash can `resume()` and finish without
      re-downloading completed pages.
- [ ] Run counters equal the sum of job counters.
- [ ] Completion triggers analytics + a notification.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncRunServiceTest`: job creation from plan, wave dispatch by budget,
  counter aggregation, finalize, resume, cancel, double-finish safety.
- **Feature** `SyncRunFlowTest` with `Http::fake()` + queue fakes: full small run
  end-to-end; window-exhaustion → scheduled resume.

## 9. Notes & open questions
- Umbrella run vs many runs: one `SyncRun` with jobs (chosen) so PIPE-09 shows a
  single progress experience.
- Analytics trigger must run after the last job commits; use a queued
  `RecalculateAnalyticsJob` (ANA-03).
