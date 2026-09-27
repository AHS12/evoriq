# SYNC-14 — Pipeline event integration

- **Status:** Draft
- **Epic:** sync
- **Estimate:** S
- **Depends on:** SYNC-04, PIPE-01
- **Blocks:** PIPE-05, SYNC-18
- **TDR:** §35, §36, §42

## 1. Why

Sync runs must appear in the same live timeline as imports/exports, so the user
sees one consistent progress experience. This spec wires sync run/job lifecycle
into the shared pipeline event stream.

## 2. Scope

**In**
- Emit PIPE events for sync lifecycle: run started, job started, stage/pages
  progress, warnings (rate limit), retries, job completed, run completed/failed.
- Map a `SyncRun` to the `PipelineRunResource` contract (`run_type=sync`).
- No new UI (PIPE-05 already renders the stream).

**Out**
- The event store (PIPE-01) and UI (PIPE-05).

## 3. Data model
- `pipeline_events` with `run_type='sync'`, `run_id=clockify_sync_runs.id`
  (PIPE-01). Sync-specific columns stay on the sync tables.

## 4. Backend
- **Emitter** `Services\Sync\SyncEventEmitter` wrapping `PipelineEventRecorder`:
  - run events → `run_id = run.id`, stage = phase name;
  - job events → include `context.entity_type`, `context.job_id`,
    `context.user_clockify_id` (for per-user time-entry jobs).
- **Aggregation:** extend PIPE-02's aggregator to derive `stages` from sync jobs
  (each job = a stage) and to compute run-level progress as
  `Σ processed / Σ expected`.
- **Progress source:** job `records_processed` + `expected` (from plan) so the
  run progress bar is determinate even across many jobs.

## 5. Frontend / UI
- Sync runs open at the same run page (PIPE-05) via a `run_type`-aware route or
  a sync-specific detail route that reuses the components.

### A11y & i18n
- Stage/entity labels translated.

## 6. API / routes / props
- PIPE resource gains `run_type: 'sync'` and sync-specific `entity/stage`
  labels; existing props unchanged otherwise.

## 7. Acceptance criteria
- [ ] A sync run produces a complete event trail and a live progress bar.
- [ ] Per-job stages are visible in the run timeline.
- [ ] Rate-limit waits appear as warnings with retry info.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncEventEmitterTest`: event mapping per lifecycle step.
- **Feature** `SyncPipelineRunTest`: aggregator derives stages/progress from
  seeded sync jobs.

## 9. Notes & open questions
- Decide the run route: reuse `/activity/{id}` with a type discriminator vs a
  dedicated `/sync/{run}` page that reuses the same components. Recommendation:
  a shared route keyed by type.
