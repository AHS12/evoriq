# SYNC-13 — Failure, retry, resume & reaper policy

- **Status:** Done
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-04, SYNC-09
- **Blocks:** SYNC-18, PIPE-07
- **TDR:** §23, §38, §41

## 1. Why

Long pipelines fail (network, rate limits, worker loss). The system must retry
safely, resume from checkpoints, never double-apply, and never leave a job
"running" forever. This is the reliability contract that makes the pipeline
trustworthy.

## 2. Scope

**In**
- Retry/backoff policy for sync jobs (attempt caps, backoff, budget-aware).
- Resume from the last checkpoint after failure/crash.
- A stale-job **reaper** that fails jobs whose worker was lost.
- Terminal-state guarantees + notifications (PIPE-11).

**Out**
- Job internals (SYNC-04), orchestration (SYNC-09), the recovery UX (PIPE-07).

## 3. Data model
- `clockify_sync_jobs.attempt/next_retry_at/last_error/status`,
  `heartbeat_at` (SYNC-01).

## 4. Backend
- **Trait** `Jobs\Concerns\TracksSyncJob` (mirrors the DPC
  `TracksDataProcessingJob`):
  - `failed(Throwable)` → if attempts remain, set `retry_scheduled` +
    `next_retry_at` and re-dispatch; else `failed` and notify.
  - Never marks failure inside `handle()`; only terminal on exhaustion/timeout.
- **Backoff:** exponential with jitter, clamped; rate-limit/`budget_wait`
  failures defer to the window reset instead of counting as an attempt.
- **Resume:** re-dispatch reads `job.page` and continues (SYNC-04). A
  `resume(run)` (SYNC-09) re-queues all non-final jobs.
- **Reaper:** `sync:reap-stale` (every 5 min) fails jobs where
  `status=running` and `heartbeat_at < now - config('clockify.stale_after')`,
  then triggers run finalization/resume.
- **Timeouts:** jobs use `#[FailOnTimeout]` with the heavy channel config
  (`QueueRegistry`); `retry_after` > timeout (project convention).
- **Idempotency:** re-running a page is safe (SYNC-08).
- **Events/notifications:** `RETRY_SCHEDULED`, `FAILED` to PIPE (SYNC-14) and the
  owner (PIPE-11) with a mapped reason (CONN-07).

## 5. Frontend / UI
- PIPE-07 renders retry/backoff/resume; sync runs inherit it.

### A11y & i18n
- Reason/hint translated.

## 6. API / routes / props
- `sync:reap-stale` command; run/job state via PIPE resource.

## 7. Acceptance criteria
- [x] A transient failure retries with backoff and resumes at the checkpoint.
- [x] A worker crash leaves no job "running" beyond `stale_after`.
- [x] Rate-limit failures wait for the window instead of burning attempts.
- [x] Every job reaches exactly one terminal state.
- [x] `composer check` passes.

## 8. Tests
- **Feature** `SyncRetryResumeTest`: fail at page N, retry, assert no duplicates
  and completion.
- **Feature** `ReapStaleSyncJobsTest`: stale running job is failed and the run
  resumes.

## 9. Notes & open questions
- Align `clockify.stale_after` with the heavy channel timeout and the existing
  DPC `exports.stale_after` convention.

### Implemented notes

- **No blocking on budget (critical for Free mode).** `ClockifyClient` gained a
  `deferBudget()` mode; sync jobs enable it for the duration of a run. When the
  window cannot afford a request the client throws `SyncBudgetExhausted` instead
  of sleeping (the previous blocking wait could exceed the heavy job timeout and
  kill the worker). `SyncJobRunner` catches it and **parks** the job
  (`status=pending`, `next_retry_at=window reset`, attempt restored) so the run
  resumes at the next window without burning an attempt.
- **Retry/backoff** lives in `SyncRetryPolicy` (rather than a trait): a
  transient failure schedules `retry_scheduled` with exponential backoff + jitter
  up to `clockify.sync_job.max_attempts`; on exhaustion the job reaches a single
  terminal `failed`. `SyncEntityJob::failed()` applies the policy and re-dispatches
  itself with the backoff delay when retrying.
- **Resume** reuses the existing checkpoint (`job.page`); re-dispatched jobs
  continue from the last completed page (SYNC-04), and re-running a page is safe
  (SYNC-08).
- **Reaper** `sync:reap-stale` (scheduled every five minutes) finds `running`
  jobs whose heartbeat is older than `clockify.sync_job.stale_after` (900s),
  reschedules them (or fails them when attempts are exhausted) and triggers run
  finalization/resume.
- **Resume safety net:** a parked run normally resumes through a delayed
  `ResumeSyncRunJob`, but that depends on a worker being alive at the exact
  reset. `sync:resume` (scheduled every minute) re-drives any non-final run that
  still has dispatchable work (a pending job, or a due retry), so a Free-plan
  import always continues after the window resets even if the worker restarted
  or the delayed job was lost. `composer run dev` now also runs `schedule:work`,
  so this works locally without extra setup.
- **Config:** `clockify.sync_job.{max_attempts,retry_base_seconds,retry_max_seconds,stale_after}`.
- **PIPE-11/14:** retry/park/failure writes the corresponding pipeline events
  (`retry_scheduled`, `warning`, `error`, terminal) via `SyncEventEmitter`.
