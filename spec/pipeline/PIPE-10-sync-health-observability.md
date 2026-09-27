# PIPE-10 — Sync & pipeline health observability

- **Status:** Draft
- **Epic:** pipeline
- **Estimate:** M
- **Depends on:** PIPE-02
- **Blocks:** OPS-01, OPS-04
- **TDR:** §42, §41

## 1. Why

Operators and developers need one screen that answers "is the pipeline
healthy?" without reading logs: success rate, durations, queue depth, stale
runs, recent failures, API budget and a way to jump from a symptom to the exact
run. TDR §42 sketches this; the existing Developer page already hosts health
checks. This spec adds a focused pipeline-health surface so long-running work is
operable, not just observable in theory.

## 2. Scope

**In**

- A `GET /developer/pipeline` page (`developer.pipeline`) gated by
  `developer.view`.
- KPI cards: active now, queued, runs today, success rate (7 d), avg/p95
  duration, records processed (7 d), failures (24 h), stale runs.
- Queue depth per channel (`critical`, `default`, `heavy`) and failed-jobs count.
- Recent runs table linking to `activity.show`.
- Recent failures with reason and retry action.
- API budget slot (populated by SYNC-02; placeholder until then).
- A `PipelineHealthCheck` integrated into the existing health list.
- Correlation-id search that jumps to the run / audit log.
- Links to Telescope, Pulse and Horizon (existing developer tools).

**Out**

- Metric storage/exports (OPS-01 wires Pulse).
- Alerting thresholds (OPS-04).
- The user-facing indicator (PIPE-08).

## 3. Data model

- None. Reads existing job/event tables; queue depth reads the queue backend.
- Optional: a small `pipeline_health_snapshots` table if trend charts are
  wanted later — defer.

## 4. Backend

- **Service:** `Services\Pipeline\PipelineMetricsService::snapshot(): array`
  (cached `pipeline.metrics_cache_seconds`, default 15):
  - status counts, 7-day success rate, avg/p95 `duration_ms`,
    records processed (`success_count` sum), failures last 24 h;
  - stale count (`processing` with stale heartbeat);
  - top failure reasons (from `failure_reason`).
- **Queue depth:** `Services\Pipeline\QueueDepthReader` reading, in order of
  preference, Horizon's API/`queue:monitor` output, otherwise the `jobs` table
  count per channel. Return `{ channel, depth }[]`, graceful when unavailable
  (e.g. `database` driver with no Horizon).
- **Health check:** `App\Checks\PipelineHealthCheck` (stale runs below a
  threshold, failure rate below a threshold, queue depth below a cap) registered
  in `HealthServiceProvider`.
- **Routes:** `developer.pipeline` under the existing `EnsureDeveloperAccess`
  middleware; add a card/link on `pages/admin/settings/developer.tsx` (or the
  developer page) and to the sidebar Developer section if present.
- **Controller:** `Developer\PipelineController@index` — thin; one service call.
- **Resource:** `PipelineHealthResource` (or a plain array prop).

## 5. Frontend / UI

**Files**

```text
resources/js/pages/developer/pipeline.tsx
resources/js/components/developer/pipeline-health.tsx
resources/js/components/developer/pipeline-metric-cards.tsx
resources/js/components/developer/queue-depth.tsx
resources/js/components/developer/recent-failures.tsx
resources/js/components/developer/correlation-search.tsx
resources/js/types/pipeline-health.ts
```

**Layout**

- Header with a health badge (Healthy / Degraded / Unhealthy) derived from the
  checks, plus the last refresh time and a manual "Refresh".
- KPI card grid (FND-05 metric marketing-style cards with optional sparkline).
- Two-column: queue depth + API budget | recent failures.
- Recent runs table (status, type, entity, duration, records, owner, started,
  link to run).
- Health checks list (reuse `health-list.tsx`) including `PipelineHealthCheck`.
- Tool links: Telescope, Pulse, Horizon, Audit log.

**Behaviour**

- Auto-refresh every 15–30 s while visible (use PIPE-03 `useLivePoll`), with
  manual refresh.
- Correlation search accepts an id/prefix and offers "Open run" when it matches
  a run, else "Search audit log".

### States

- Queue backend unavailable → show "Queue metrics unavailable (driver: …)"
  rather than an error.
- No runs yet → empty state pointing to the import wizard.

### A11y & i18n

- Health state conveyed by icon + text, not colour alone.
- Tables keyboard navigable; sparklines have text alternatives (trend value).
- All strings translated in the five `lang/app/*.json`.

## 6. API / routes / props

- `GET /developer/pipeline` (`developer.pipeline`) → `{ metrics, queue, health,
  recentRuns, recentFailures, apiBudget, tools }`.
- Poll via partial reload `only: ['metrics','queue','health']`.

## 7. Acceptance criteria

- [ ] The page renders for developers and 403s otherwise.
- [ ] KPI cards reflect seeded jobs correctly.
- [ ] Queue depth shows per-channel values or a graceful message.
- [ ] Recent failures link to the run page and expose retry.
- [ ] `PipelineHealthCheck` appears in the health list and can degrade status.
- [ ] Correlation search navigates to the right destination.
- [ ] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/PipelineMetricsServiceTest.php`: success rate, p95,
  counts, failure reasons, stale detection.
- **Feature** `tests/Feature/Developer/PipelineHealthPageTest.php`: authorization
  (developer vs non-developer), prop shape, health check registration.

## 9. Notes & open questions

- p95 on SQLite (tests) needs a portable implementation; use a window function
  where supported and a fallback for SQLite.
- Decide how much of this should be visible to non-developer users with
  `data-processing.view.all` (recommendation: a reduced, non-technical summary
  later).
- API budget source is SYNC-02; until then render it as "not configured".
