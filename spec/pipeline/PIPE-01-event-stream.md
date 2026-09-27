# PIPE-01 — Pipeline event stream

- **Status:** Draft
- **Epic:** pipeline
- **Estimate:** L
- **Depends on:** —
- **Blocks:** PIPE-02, PIPE-05, PIPE-06, SYNC-14
- **TDR:** §23, §35–38, §42

## 1. Why

`DataProcessingJob` only stores *aggregate* progress (`stage`, `total_items`,
`processed_items`, `errors`). That is enough for a progress bar but not for the
flagship "world's best timeline" experience: ordered stages, per-stage
durations, retries, warnings, artifacts and an auditable history of what the
pipeline actually did. This spec introduces one **append-only event stream**
that every background run (imports, exports and, later, Clockify syncs) writes
to. It is the data foundation for PIPE-04/05/06 and makes long runs explainable
and debuggable instead of a black box.

## 2. Scope

**In**

- A `pipeline_events` table plus append API.
- A `PipelineEventType` / `PipelineEventLevel` / `PipelineRunType` enum set.
- A `PipelineEventRecorder` service with **coalescing** and **redaction**.
- Retrofit of the existing Data Processing Center flow to emit events at every
  meaningful transition.
- `config/pipeline.php` for retention + coalescing + redaction knobs.

**Out**

- The UI that renders events (PIPE-04/05/06).
- Sync-run events (SYNC-14 wires Clockify sync into the same stream).
- Real-time transport (PIPE-03).
- Event notification (PIPE-11).

## 3. Data model

Migration `create_pipeline_events_table`:

```text
pipeline_events
  id                 bigint pk
  run_type           string        # enum PipelineRunType (indexed with run_id)
  run_id             string        # string so it fits any run table's id
  sequence           unsigned bigint
  type               string        # enum PipelineEventType
  level              string        # enum PipelineEventLevel, default "info"
  stage              string|null   # free-form stage key (e.g. "fetch", "parse")
  message            text|null     # user-facing, already translated key or text
  context            json|null     # structured details (redacted)
  progress           json|null     # { total, processed, percentage }
  attempt            unsigned smallint default 1
  duration_ms        unsigned int|null
  occurred_at        timestamp
  created_at         timestamp
```

Indexes / constraints:

- unique `(run_type, run_id, sequence)` — guarantees an ordered, gap-tolerant log.
- `(run_type, run_id, occurred_at)` — timeline reads.
- `(level)` — error filtering.

Notes:

- `run_id` is a **string** by design so `data_processing_jobs.id` today and
  `clockify_sync_runs.id` tomorrow share one stream without polymorphic FKs.
  Trade-off: no FK, so the run tables own their events' lifecycle (prune by
  `run_type`/`run_id`).
- `sequence` is allocated per run; the recorder owns allocation.

Enums:

```php
enum PipelineRunType: string { case DATA_PROCESSING = 'data_processing'; case SYNC = 'sync'; }

enum PipelineEventLevel: string { case DEBUG='debug'; case INFO='info'; case SUCCESS='success'; case WARNING='warning'; case ERROR='error'; }

enum PipelineEventType: string {
    case DISPATCHED; case STARTED;
    case STAGE_STARTED; case PROGRESS; case STAGE_COMPLETED;
    case INFO; case WARNING; case ERROR;
    case RETRY_SCHEDULED; case RETRY_STARTED;
    case ARTIFACT_READY;
    case COMPLETED; case CANCELLED; case FAILED;
}
```

## 4. Backend

- **Models:** `App\Models\PipelineEvent` (cast `type`, `level`, `run_type`,
  `context`/`progress` arrays, `occurred_at`).
- **Enums:** `App\Enums\PipelineRunType`, `PipelineEventLevel`,
  `PipelineEventType` (labels/icon names wrapped in `__()`).
- **DTO:** `App\DTOs\Pipeline\PipelineEventDTO` (readonly; `toArray()`).
- **Repository:** `Contracts\PipelineEventRepositoryInterface` +
  `Repositories\Pipeline\PipelineEventRepository`:
  - `append(array $data): PipelineEvent`
  - `nextSequence(PipelineRunType $type, string $runId): int`
  - `forRun(PipelineRunType $type, string $runId, int $perPage = 50, array $filters = []): LengthAwarePaginator`
  - `latestForRuns(array $runKeys): array` (for the global indicator)
  - `pruneBefore(CarbonInterface $cutoff): int`
  Bind in `RepositoryServiceProvider`.
- **Service:** `App\Services\Pipeline\PipelineEventRecorder`:
  - `record(PipelineRunType $type, string|int $runId, PipelineEventType $event, array $options = []): void`
    options: `message`, `level`, `stage`, `context`, `progress`, `attempt`,
    `duration_ms`, `occurred_at`.
  - Convenience: `dispatched()`, `started()`, `stageStarted()`, `progress()`,
    `stageCompleted()`, `warning()`, `error()`, `retryScheduled()`,
    `artifactReady()`, `completed()`, `failed()`, `cancelled()`.
  - **Coalescing** for `PROGRESS`: skip the write unless the stage changed, the
    percentage crossed a bucket boundary (`pipeline.progress_event_bucket`,
    default 5), or `pipeline.progress_event_min_seconds` (default 5) elapsed
    since the run's last progress event. Read the last event via the repository.
  - **Redaction:** recursively remove keys listed in
    `config('pipeline.redacted_attributes')` from `context` before persisting.
  - Events are written outside `DB::transaction` alarms: **never inside a
    transaction that can roll back**, so the log stays truthful (write after
    commit, same rule as mail/notifications in `AGENTS.md` §7.9).
- **Config:** `config/pipeline.php` — `progress_event_bucket`,
  `progress_event_min_seconds`, `event_retention_days` (default 30),
  `redacted_attributes` (default: `api_key`, `token`, `authorization`,
  `password`, `secret`).
- **Retrofit `DataProcessingJobService`:**
  - `createAndDispatch()` → `dispatched` (after commit).
  - `markProcessing()` → `started` (only on first transition).
  - `markProgress()` → `progress` (coalesced) with stage.
  - `attachArtifact()` → `artifactReady`.
  - `markCompleted()` → `completed`; `markFailed()` → `failed`;
    `markCancelled()` → `cancelled`; `retry()` → `retryScheduled`.
- **Retrofit jobs:**
  - `ProcessExport`: `stageStarted('prepare')`, `stageStarted('generate')`,
    `stageCompleted(...)` around phases; `warning` on recoverable issues.
  - `ProcessImport`: `stageStarted('read')` / `('write')`; `progress` callback
    also emits events (coalesced) and updates heartbeat.
  - `Concerns/TracksDataProcessingJob::failed()`: `error` then
    `retryScheduled` (if retries remain) or `failed`.
- **Model helper:** `DataProcessingJob::pipelineRunType(): PipelineRunType` and
  `pipelineRunId(): string` (implement a small `PipelineRunnable` contract) so
  services can pass the model directly to the recorder.

## 5. Frontend / UI

None in this spec (consumed by PIPE-02/05/06). The only visible change is that
existing pages keep working; the event data is available server-side.

### A11y & i18n

- `PipelineEventType::label()` returned via `__()` so the frontend can `t()` it.

## 6. API / routes / props

- No new public routes in this spec. The recorder is internal.
- (PIPE-06 will expose a paginated events endpoint gated by
  `DataProcessingJobPolicy::view`.)

## 7. Acceptance criteria

- [ ] Dispatching an import/export writes a `dispatched` event.
- [ ] A run's events have strictly increasing `sequence` with no duplicates
      under concurrent writes (allocation is race-safe).
- [ ] Progress events are coalesced: a 10,000-chunk import does **not** produce
      10,000 events.
- [ ] Redacted keys never appear in `context`.
- [ ] Completed/failed/cancelled runs always end with exactly one terminal
      event.
- [ ] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/PipelineEventRecorderTest.php`:
  - records events with correct run type/id and monotonic sequence;
  - coalescing skips rapid progress writes but writes on stage change/bucket;
  - redaction strips configured keys (including nested);
  - convenience methods map to the right `type`/`level`.
- **Feature** `tests/Feature/Pipeline/PipelineEventStreamTest.php`:
  - run a `ProcessExport`/`ProcessImport` with `Http`/`Excel` fakes (reuse
    `tests/Mock/*`) and assert the event sequence for the run;
  - failure path ends with `error` + `retryScheduled`/`failed`.
- Extend `tests/Unit/DataProcessingJobServiceUnitTest.php` to assert recorder
  calls (Mockery) for transitions.

## 9. Notes & open questions

- Confirm the coalescing knobs (5% / 5s) are right for very large imports; may
  need a `debug_events` flag for troubleshooting.
- `run_id` as string loses referential integrity; the scheduled prune (PIPE-12)
  must clean orphaned events by run type.
- Consider `occurred_at` vs `created_at`: `occurred_at` is the logical time the
  step finished; `created_at` is insert time. Both are kept.
