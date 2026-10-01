# PIPE-08 — Global pipeline indicator

- **Status:** Done
- **Epic:** pipeline
- **Estimate:** M
- **Depends on:** PIPE-03
- **Blocks:** DASH-05, PIPE-09
- **TDR:** §14, §35–37

## 1. Why

Background work should be visible from anywhere, calmly. Today the sidebar
shows a bare `activeJobs` number. A user who starts an import and navigates
elsewhere loses all sense of progress. A single global indicator — a live dot,
a count, a popover of active runs, plus the reserved slots for "data through"
freshness and Clockify API budget — keeps the promise that work is happening
without being intrusive.

## 2. Scope

**In**

- A header **pipeline indicator** (live dot + count + worst status) and popover.
- Sidebar "Job activity" live treatment (count + animated dot).
- A cached, shared `pipelineStatus` Inertia prop so every page can show it and
  poll it cheaply.
- The popover lists the top active runs with mini progress + stage + ETA, and a
  "View all" link.
- Reserved, clearly-scoped slots for **Data through / Last synced** (TDR §35)
  and **API budget** (TDR §14/§37), rendered only when their data exists.

**Out**

- Computing freshness/API budget (CONN/SYNC phases provide the data).
- The full run page (PIPE-05).
- Notification bell (existing).

## 3. Data model

None. A short-lived cache entry backs the shared prop.

## 4. Backend

- **Service:** `Services\Pipeline\PipelineStatusService::summary(): array`:
  - counts by status (active, queued, failed in last 24 h);
  - `worst_status` for tone (`error` > `warning` > `info` > `success`);
  - top 5 active runs (id, name, type, percentage, stage, eta_seconds);
  - result cached for `config('pipeline.status_cache_seconds')` (default 5) and
    invalidated on run state changes.
- **Shared prop:** add `pipelineStatus` in
  `App\Http\Middleware\HandleInertiaRequests` (authenticated only), using the
  service. Keep the existing `activeJobs` for compatibility (derive from it) or
  migrate consumers to `pipelineStatus.active_count`.
- **Resource:** `App\Http\Resources\Pipeline\PipelineStatusResource`.
- Ensure `only: ['pipelineStatus']` partial reloads are cheap (cache hit).
- **Reserved shapes:**
  - `freshness: { last_synced_at, data_through, next_sync_at } | null`
  - `api_budget: { used, remaining, limit, resets_at } | null`

## 5. Frontend / UI

**Files**

- `components/pipeline/pipeline-indicator.tsx` — header trigger + popover.
- `components/pipeline/pipeline-status-popover.tsx` — active-run list.
- `components/pipeline/pipeline-live-dot.tsx` — pulsing dot from FND-01.
- `hooks/use-pipeline-status.ts` — subscribes to shared prop + `useLivePoll`.
- Update `components/app-sidebar.tsx` and `components/app-sidebar-header.tsx`.

**Experience**

- **Idle:** indicator hidden entirely (or a very subtle dot only when
  `failed_recent > 0` to nudge attention).
- **Active:** a small pill `● 3 running` with a toned dot; the sidebar item
  shows the same count and an animated dot.
- **Popover:** header "Pipeline" + status line; a list of active runs with
  name, entity, thin progress bar, stage, ETA; footer link "View all activity".
  When `failed_recent > 0`, a "N recent failures" row links to the Failed
  filter.
- **Freshness slot:** when present, "Data through Sep 23" with a relative
  "synced 12m ago" and a "Sync now" affordance (wired in SYNC-12).
- **API budget slot:** when present, `API 41/50` with a link to the budget
  popover (TDR §37).
- **Mobile:** the indicator collapses into the sidebar sheet / header sheet.

**States**

- Polling paused: static values with a small "paused" hint.
- No active runs but failures: muted with an error-toned dot.
- Loading: skeleton pill.

### A11y & i18n

- Trigger is a button with an accessible label ("Background jobs: 3 running").
- The live dot is decorative; the count text carries meaning.
- The popover is keyboard-operable and closes on Esc; focus returns to trigger.
- Announce only on meaningful transitions (new failure, all runs finished), not
  every poll.
- All strings translated in the five `lang/app/*.json`.

## 6. API / routes / props

- Shared prop `pipelineStatus` on all authenticated pages; polled via
  `only: ['pipelineStatus']`.
- No new route.

## 7. Acceptance criteria

- [x] Indicator appears in the header on every authenticated page and updates
      live while runs are active.
- [x] Sidebar badge and header count stay in sync from one poll.
- [x] Popover lists active runs with live progress and links to the run page.
- [x] `failed_recent` surfaces a clear path to the failed view.
- [x] Freshness and API-budget slots render only when data exists and are
      extensible.
- [x] Indicator is hidden/quiet when idle.
- [x] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/PipelineStatusServiceTest.php`: counts, `worst_status`
  precedence, top-5 selection, cache behaviour.
- **Feature** `tests/Feature/Pipeline/PipelineStatusPropTest.php`: the shared
  prop is present for authenticated users and absent for guests; only contains
  the current user's/global summary as designed.
- Update sidebar-related feature tests if they assert `activeJobs`.

## 9. Notes & open questions

- Aggregate query cost on every page: mitigate with the 5 s cache and a covering
  index on `data_processing_jobs(status, created_at)`.
- Decide whether failures should always be visible globally (recommendation:
  yes, but muted).
- The indicator is also the future home of sync freshness; keep it a data-driven
  slot, not hard-coded to jobs.
