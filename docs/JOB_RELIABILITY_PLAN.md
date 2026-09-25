# Plan — Job reliability: stuck / failed jobs, retries & chunking

**Status:** Implemented — `composer check` green (327 tests)
**Scope:** Make `DataProcessingJob` rows always reach a terminal state (completed /
failed / cancelled) even when the queued job times out, exhausts attempts, or the
worker dies; make retries safe; and make import progress update sooner.
**Related:** `docs/DATA_PROCESSING_CENTER_PLAN.md`, `AGENTS.md` §7.9 / §7.14,
`config/queue.php`, `app/Jobs/ProcessImport.php`, `app/Jobs/ProcessExport.php`

---

## 1. What we observed

- A **Users import** was left stuck on **Processing · 0 / 1000 · 0%** in the Job
  activity UI, forever (never flips to Failed).
- `php artisan queue:failed` shows a failed `ProcessImport` on `redis@heavy`.
- The user's description: the job was "rejected by Laravel (too many attempts)".

## 2. Root cause

| Fact | Value |
| --- | --- |
| Queue connection | `redis` (`.env`) |
| Connection `retry_after` | **90s** (default; no env override) |
| `heavy` channel `timeout` | **1800s** (`QUEUE_HEAVY_TIMEOUT`) |
| `heavy` channel `tries` | 1 |

Laravel requires **`retry_after > job timeout`** (docs: "the `--timeout` value
should always be several seconds shorter than your `retry_after`"). Here the
opposite is true: a job that runs longer than **90s** is considered "lost" and
**re-reserved by another worker while the first worker is still running it**.

Consequences:

1. **Duplicate dispatch** — two workers run the same import.
2. When attempts blow past the limit, the worker raises
   `MaxAttemptsExceededException` ("attempted too many times or run too long")
   and moves the job to `failed_jobs` — **without ever calling our `handle()`**
   on that final attempt.
3. Our jobs have **no `failed()` method** and don't opt into `FailOnTimeout`, so
   the `DataProcessingJob` row is **never updated** → stuck `PROCESSING`.
4. Timeouts (Linux/pcntl) hit the same gap: the worker kills the process; unless
   the job opts into *fail on timeout*, our `handle()` catch never runs.

> On Windows dev there is no `pcntl`, so `--timeout`/timeouts never fire at all;
> the duplicate-dispatch path (#1/#2) is what bites there.

## 3. Research (what Laravel gives us)

- **`retry_after` is per connection and must exceed the longest job timeout.**
  Setting a per-job `retryAfter` is a long-standing complaint; the reliable
  pattern is either one connection-wide value above the max timeout, or a
  **separate connection** per long-running tier.
- **`#[FailOnTimeout]`** (or `public bool $failOnTimeout = true;`) makes a
  timed-out job **fail immediately** (no retry) instead of being released.
- The `Worker` timeout handler (`vendor/laravel/framework/.../Queue/Worker.php:319`)
  calls `markJobAsFailedIfItShouldFailOnTimeout()` → `$job->fail($e)` when the
  job opts in, which invokes the job's **`failed(Throwable $e)`** method.
- **`failed(Throwable)` on the job class** is the supported hook to update our
  own domain row when the job ultimately fails (after tries exhausted / timeout).
- A **`Queue::failing(JobFailed $event)`** listener is a global alternative, but
  we can't reliably map the failed payload back to our row and it fires for all
  jobs — the per-job `failed()` hook is cleaner.
- No hook fires if the process is **SIGKILL'd / OOM / machine dies** → a
  **scheduled "reaper"** that fails stale `PROCESSING` rows is the accepted
  safety net.

## 4. Fixes

### A. Queue configuration (root cause) — **highest priority**
- Introduce `QUEUE_RETRY_AFTER` (default **`1860`** = heavy timeout 1800 + 60)
  and use it for the `redis` **and** `database` connections' `retry_after`.
- Add it to `.env` and `.env.example`, documented.
- Add a guard test: for every channel, `connection.retry_after > channel.timeout`.
- Note the trade-off: a *crashed* short job waits up to `retry_after` before it is
  retried; that's acceptable here. (Future: a dedicated `redis-heavy` connection
  with its own worker if we want short-job recovery to stay fast — deferred.)

### B. Fail-on-timeout + `failed()` handler
- Add `#[FailOnTimeout]` to `ProcessImport` and `ProcessExport`.
- Extract a small trait, e.g. `App\Jobs\Concerns\TracksDataProcessingJob` with:
  - `failed(Throwable $e): void` — reload the row; if it's already terminal,
    return; otherwise `markFailed($job, $e->getMessage())` + `notifyFinished()`.
- Simplify `handle()`: keep the `catch (ImportCancelledException)` → cancel path,
  but **move failure marking/notification into `failed()`** (single source of
  truth) and just rethrow on other throwables. This also makes retries behave
  correctly (row stays `PROCESSING` until the final failure).

### C. Stale-job reaper (crash safety net)
- New command **`data-processing:reap-stale`**, scheduled every 5 minutes in
  `routes/console.php`.
- Finds `PROCESSING` jobs where `started_at` is older than
  `timeout + buffer` (config `exports.stale_after`, default 1800 + 120) and marks
  them `FAILED` with "Stalled — the worker was lost or timed out.", then notifies.
- Repository helper: `staleProcessingBefore(CarbonInterface $cutoff): Collection`.
- This also cleans up the rows that are **already stuck right now**.

### D. Chunk size → 100 (faster progress)
- `UserImport::chunkSize()` reads `config('exports.import.chunk_size')`.
- Add `config/exports.php` → `'import' => ['chunk_size' => env('EXPORT_IMPORT_CHUNK_SIZE', 100)]`.
- Progress now updates every 100 rows (10 updates for 1,000) instead of every 500.
- Optional micro-improvement: only write progress when the value changed
  (maatwebsite calls `collection()` per chunk, so this is already once per chunk).

### E. UI (small)
- No structural change needed: `failed()` + the reaper update the row, and the
  existing polling picks it up, so a failed job flips to **Failed** with the
  error in the drawer.
- Optional: show an inline "stalled" hint if `status = processing` and
  `started_at` is older than the timeout (client-side), so a genuinely hung job
  is visibly flagged before the reaper runs. (Nice-to-have.)

## 5. Tests

- **Config guard** (unit): every channel's `timeout` < its connection `retry_after`.
- **`failed()` handler** (feature): invoking `ProcessImport::failed($e)` marks the
  row `FAILED`, stores the message, and creates a notification; a terminal row is
  left untouched.
- **Reaper** (feature): a `PROCESSING` row older than the threshold becomes
  `FAILED` (+ notification); a fresh `PROCESSING` row and terminal rows are
  untouched.
- **Chunk size** (unit): `UserImport::chunkSize()` returns 100 by default and
  honors the config/env; the 520-row smoke test still completes.
- Re-run the 1,000-row stress import end-to-end.

## 6. Sequencing

1. **A** — queue config fix + guard test (stops the bleeding).
2. **B** — `FailOnTimeout` + `failed()` trait on both jobs.
3. **C** — reaper command + schedule (cleans existing stuck rows).
4. **D** — configurable chunk size (100).
5. **E** — optional stalled hint.
6. `composer check`; re-run the stress import.

## 7. Open questions

- **Heavy retries:** keep `tries = 1` (no auto-retry; user uses "Run again") or
  allow a small retry? Recommendation: keep **1** — imports are expensive and
  re-runnable via the UI, and duplicate-safe.
- **`retry_after` strategy:** one connection-wide `1860` (recommended, simple) vs
  a dedicated `redis-heavy` connection (better isolation, extra worker/complexity).
- **Reaper threshold:** `timeout + 120s` (recommended) or based on `updated_at`.
