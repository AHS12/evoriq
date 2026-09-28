# PIPE-02 — Run aggregation & progress contract

- **Status:** Done
- **Epic:** pipeline
- **Estimate:** L
- **Depends on:** PIPE-01
- **Blocks:** PIPE-03, PIPE-05, PIPE-06, PIPE-07, PIPE-08, PIPE-10, PIPE-11
- **TDR:** §23, §35–38

## 1. Why

PIPE-01 records what happened; this spec turns it into a single, consistent
**run contract** the whole frontend renders: status, stage breakdown, live
progress, ETA, throughput, staleness, retry state and a typed failure reason.
Today the frontend recomputes ETA itself (`computeEta` in `job-utils.ts`) and
has no notion of stages, attempts, heartbeat or *why* a run failed. A single
server-computed contract is what makes the timeline honest and the UI simple.

## 2. Scope

**In**

- New run-state columns on `data_processing_jobs`: `attempt`,
  `last_heartbeat_at`, `next_retry_at`, `failure_reason`.
- A `PipelineFailureReason` enum with friendly title + hint + suggested action.
- Server-side progress math: elapsed, ETA, throughput, staleness.
- Stage derivation from `pipeline_events`.
- A `PipelineRunResource` (superset of the current `DataProcessingJobResource`)
  returned by `/activity`, `/exports` and the job detail endpoints.
- Liveness heartbeat written during processing.

**Out**

- Rendering (PIPE-05/06).
- Polling transport (PIPE-03).
- Changing the underlying retry mechanics (PIPE-07 does the UX; queue config
  stays as is).

## 3. Data model

Migration `add_pipeline_state_to_data_processing_jobs`:

```text
data_processing_jobs
  + attempt            unsigned smallint   default 0
  + last_heartbeat_at  timestamp nullable
  + next_retry_at      timestamp nullable
  + failure_reason     string nullable     # enum PipelineFailureReason
```

- `attempt` is incremented on each pick-up (`markProcessing`).
- `last_heartbeat_at` is refreshed on every progress write and stage change.
- `next_retry_at` is set by `TracksDataProcessingJob` when a retry is scheduled
  (derived from the queue backoff) and cleared on the next start.
- `failure_reason` is set together with `error_message` on failure.

Enum `App\Enums\PipelineFailureReason`:

```text
api_unavailable | rate_limited | auth_failed | network | timeout
validation | source_missing | worker_lost | cancelled | unknown
```

Each case exposes `label()`, `hint()` and `action()` (e.g. `retry`, `reconnect`),
all wrapped in `__()`.

## 4. Backend

- **Model:** casts for the new columns; helpers `isStale()`, `attemptsLabel()`,
  `heartbeatAge()`; keep `progressPercentage()`.
- **DTOs:**
  - `PipelineProgressDTO` — `{ total, processed, percentage, indeterminate,
    elapsedSeconds, etaSeconds, throughputPerMin }`.
  - `PipelineStageDTO` — `{ key, label, status, startedAt, endedAt, durationMs,
    processed, total }`.
  - `PipelineTimingDTO` — `{ dispatchedAt, startedAt, completedAt, durationMs,
    lastHeartbeatAt, stale }`.
- **Service:** extend `DataProcessingJobService` (or add
  `Services\Pipeline\PipelineRunAggregator`) to build the contract:
  - derive stages by folding `pipeline_events` (`STAGE_STARTED` →
    `STAGE_COMPLETED`, latest `PROGRESS` per stage);
  - compute ETA from throughput since the current stage started; return `null`
    when fewer than `pipeline.eta_min_samples` (default 2) progress points or
    when `total` is unknown/0;
  - compute `stale = processing && last_heartbeat_at < now - pipeline.stale_after`
    (align with `exports.stale_after` used by the reaper);
  - resolve `failure_reason`, `hint` and `action`.
  - All reads go through `PipelineEventRepository` / `DataProcessingJobRepository`
    — no queries in the service.
- **Failure mapping:** `App\Services\Pipeline\FailureReasonResolver` maps
  exceptions/messages (e.g. `ApiException` `CLOCKIFY_RATE_LIMITED` →
  `rate_limited`, `MaxAttemptsExceededException` → `worker_lost`,
  `ImportCancelledException` → `cancelled`) to a `PipelineFailureReason`.
- **Heartbeat:** `markProgress()` and stage transitions update
  `last_heartbeat_at`; the existing `data-processing:reap-stale` command keeps
  using `started_at` but should prefer `last_heartbeat_at` when present.
- **Resource:** `App\Http\Resources\Pipeline\PipelineRunResource` (keep the
  existing `DataProcessingJobResource` delegating to it, or replace usage
  incrementally). Includes a bounded `timeline` (latest N events, default 50)
  using `PipelineEventResource`.

Contract shape (JSON):

```json
{
  "id": 42,
  "run_type": "data_processing",
  "type": "import",
  "status": "processing",
  "name": "Users import",
  "entity": { "value": "users", "label": "Users", "icon": "users" },
  "stage": "write",
  "progress": { "total": 12000, "processed": 8400, "percentage": 70, "indeterminate": false,
                "elapsed_seconds": 312, "eta_seconds": 134, "throughput_per_min": 1615 },
  "counts": { "created": 8100, "updated": 0, "failed": 12, "skipped": 288 },
  "stages": [ { "key": "read", "status": "completed", "duration_ms": 21000, "processed": 12000, "total": 12000 },
              { "key": "write", "status": "running", "duration_ms": 291000, "processed": 8400, "total": 12000 } ],
  "attempts": { "current": 2, "next_retry_at": null },
  "failure": null,
  "timing": { "dispatched_at": "…", "started_at": "…", "completed_at": null,
              "duration_ms": null, "last_heartbeat_at": "…", "stale": false },
  "abilities": { "cancel": true, "retry": false, "resume": false, "duplicate": true, "download": false, "delete": true },
  "timeline": [ /* PipelineEventResource[] */ ]
}
```

## 5. Frontend / UI

- Update `resources/js/types/data-processing.ts` (or add
  `types/pipeline.ts`) to the new contract; keep a compatibility mapper for any
  component not yet migrated.
- PIPE-05/06 consume it. `computeEta` in `job-utils.ts` is removed once the
  server provides `eta_seconds` (keep formatting only).

### A11y & i18n

- Failure reason title/hint/action come from PHP `__()` labels and are rendered
  through `t()`.

## 6. API / routes / props

- `GET /activity` (`activity.index`) — each job in `jobs.data` is now a
  `PipelineRunResource`.
- `GET /exports` and `exports.show` — same shape.
- Job detail (`PIPE-06`) returns the same contract.

## 7. Acceptance criteria

- [x] Active runs expose stage list, ETA, throughput and heartbeat.
- [x] Completed/failed runs expose duration, counts and (for failures)
      `failure.reason` + `failure.hint`.
- [x] A stale run (heartbeat older than the threshold) is reported `stale`.
- [x] `attempt` increments per pick-up and is exposed.
- [x] The existing `/activity` page renders without regression after switching
      resources.
- [x] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/PipelineRunAggregatorTest.php` — stage derivation, ETA
  (null with too few samples, numeric from stage throughput), throughput math,
  staleness, failed-run classification, bounded timeline, skipped counts and
  timing.
- **Unit** `tests/Unit/FailureReasonResolverTest.php` — every resolver branch
  (exceptions and stored messages).
- Extended `tests/Unit/DataProcessingJobServiceUnitTest.php` with the attempt
  increment/heartbeat and classified-`failure_reason` transitions.
- **Feature** `tests/Feature/Pipeline/PipelineRunResourceTest.php` — `/activity`
  exposes the contract for a processing job and the mapped failure for a failed
  job; `/exports/{job}` embeds the bounded timeline.
- **Frontend** `resources/js/components/data-processing/job-utils.test.ts` —
  `computeEta` prefers the server `eta_seconds` and keeps the local fallback.
- The `DataProcessingJobFeatureTest` shape assertions still pass unchanged
  (the resource is a superset).

## 9. Notes & open questions

- **Files:** `app/Enums/PipelineFailureReason.php`,
  `app/DTOs/Pipeline/Pipeline{Progress,Stage,Timing,Run}DTO.php`,
  `app/Services/Pipeline/{PipelineRunAggregator,FailureReasonResolver}.php`,
  `app/Http/Resources/Pipeline/{PipelineRunResource,PipelineEventResource}.php`,
  `database/migrations/2026_09_28_010000_add_pipeline_state_to_data_processing_jobs.php`.
- **Superset resource:** `PipelineRunResource` emits the aggregated contract
  **and** the legacy fields the Data Processing Center already uses, so
  `/activity` renders unchanged; `DataProcessingJobResource` now extends it
  (deprecated) and controllers return `PipelineRunResource` directly.
- **Aggregator shape:** the resource maps a `PipelineRunDTO` (the aggregator
  folds the row + `pipeline_events`). List endpoints aggregate with
  `withTimeline: false` (empty `timeline`); single-run endpoints embed the
  bounded timeline (`pipeline.timeline_limit`, default 50).
- **ETA:** estimated from the current (last running) stage's progress points;
  `null` below `pipeline.eta_min_samples` or with an unknown total.
- **Staleness:** `pipeline.stale_after` defaults to `EXPORT_STALE_AFTER` so it
  matches the reaper; the reaper's `staleProcessingBefore()` now prefers
  `last_heartbeat_at` and falls back to `started_at`.
- **Failure order:** `FailureReasonResolver` checks `worker_lost` before
  `timeout`, so the reaper's "worker was lost or it timed out" message classifies
  as `worker_lost`.
- **Pagination type:** `DataProcessingJobRepositoryInterface::paginate()` now
  returns the concrete `Illuminate\Pagination\LengthAwarePaginator` so the
  service can use `->through()` to map jobs to run DTOs.
- **`updated` count** is always `0` — there is no per-row "updated vs created"
  tracking yet (imports only report created/skipped/failed).
- **`next_retry_at`** is derived from the channel base backoff × attempt; the
  heavy channel runs with `tries: 1`, so retries are mostly manual.
- Frontend `computeEta` keeps a local fallback until every surface consumes the
  server ETA (PIPE-05 removes it entirely).
