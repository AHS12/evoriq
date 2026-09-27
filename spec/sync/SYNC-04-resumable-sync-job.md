# SYNC-04 — Resumable sync job engine

- **Status:** Draft
- **Epic:** sync
- **Estimate:** L
- **Depends on:** SYNC-01, SYNC-02, SYNC-03
- **Blocks:** SYNC-05, SYNC-14, SYNC-13, ENT-*
- **TDR:** §11, §23, §24, §41

## 1. Why

This is the "execute it, and if it crashed reload/re-run from that point"
requirement. One generic, idempotent, checkpointed job drives every entity fetch
so a worker lost at page 37 resumes at page 37 — the full import never restarts.

## 2. Scope

**In**
- A generic `SyncEntityJob` (queued, heavy channel) that fetches one entity over
  one range for one user/workspace and upserts pages.
- Per-page transaction, checkpoint advance, counters, heartbeat, cancel check.
- Resume from `clockify_sync_jobs.page`.
- Integration with `ClockifyClient`/`ClockifyPaginator`, `ApiUsageService`, raw
  store (SYNC-05), upsert repos (SYNC-08) and PIPE events (SYNC-14).
- A `SyncHandler` per entity type (fetcher + mapper + repo), resolved by
  `SyncEntityType`.

**Out**
- Planning (SYNC-03), orchestration (SYNC-09), retry policy (SYNC-13),
  reconciliation (SYNC-10).

## 3. Data model
- Reads/writes `clockify_sync_jobs` (checkpoint, counters, status).
- Writes raw records (SYNC-05) and normalized entities (ENT-*).

## 4. Backend
- **Contract** `App\Services\Sync\Contracts\SyncHandler`:
  `fetchPage(job, page): iterable`, `map(raw): array`,
  `upsert(workspace, items): UpsertCounts`, `entityType(): SyncEntityType`.
- **Service** `Services\Sync\SyncJobRunner::run(ClockifySyncJob $job)`:
  1. `markRunning` (attempt++, heartbeat); emit `STARTED`.
  2. If `job.page > 0` → resume from `job.page + 1` (never restart).
  3. Loop pages via the handler:
     - `ApiUsageService::reserve()` (wait/pace on Free);
     - fetch page; persist raw records;
     - `DB::transaction(fn () => handler->upsert(...))`;
     - `job->advance(page, counts)`, heartbeat, emit `PROGRESS` (coalesced);
     - if `cancel_requested` → stop cleanly (`CANCELLED`).
  4. On `Last-Page` true → mark `COMPLETED`, emit `STAGE_COMPLETED`.
- **Job** `App\Jobs\Sync\SyncEntityJob` — thin; `#[FailOnTimeout]`, heavy queue
  config from `QueueRegistry`; delegates to `SyncJobRunner`. `failed()` handled
  by SYNC-13.
- **Idempotency:** every upsert keyed by `(org, workspace, clockify_id)`;
  re-running a page overwrites, never duplicates (`SYNC-08`).
- **Cancel:** cooperative, checked between pages.
- **Heartbeat:** `heartbeat_at` refreshed each page → the reaper (SYNC-13) can
  detect dead workers.

## 5. Frontend / UI
- None directly; progress is rendered by PIPE-05 from the events.

### A11y & i18n
- Stage/entity labels from enums (translated).

## 6. API / routes / props
- None; dispatched by SYNC-09.

## 7. Acceptance criteria
- [ ] A job stopped after page N resumes at page N+1 with no duplicate rows.
- [ ] Each page is atomic (partial page never persisted on failure).
- [ ] Counters (created/updated/processed) are accurate per job and run.
- [ ] On Free plans the job waits for the hourly window rather than erroring.
- [ ] Cancellation stops between pages and reports `cancelled`.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncJobRunnerTest` (handler mocked): resume from page, per-page
  transaction rollback, cancel, counter aggregation.
- **Feature** `SyncJobResumeTest` with `Http::fake()` sequence: fail mid-way,
  re-run, assert no duplicates and checkpoint advanced (mirrors the
  `clockify-sync-feature` skill example).

## 9. Notes & open questions
- Page size default 200 (config); Free plans may prefer smaller pages to spread
  budget — planner decides.
- If a single page can never complete within an hourly window (huge volume),
  shrink pages (SYNC-03) rather than block.
