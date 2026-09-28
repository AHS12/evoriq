# PIPE-05 — Run timeline UI

- **Status:** Done
- **Epic:** pipeline
- **Estimate:** L
- **Depends on:** PIPE-03, PIPE-04
- **Blocks:** PIPE-06, PIPE-07, PIPE-09
- **TDR:** §34–38, §42

## 1. Why

This is the product's signature moment. When someone starts a five-year import
or a "Sync Now", the screen they watch must feel **alive, honest and
reassuring** — the exact opposite of a spinner that means nothing. This spec
delivers a dedicated, deep-linkable run page and upgrades `/activity` into a
live operation timeline that is visibly better than anything Clockify offers:
real stages, throughput, ETA, a streaming event log, explicit retries and a
clear "you can leave" promise.

## 2. Scope

**In**

- New page `resources/js/pages/data-processing/show.tsx` at `GET /activity/{job}`.
- Major upgrade of `resources/js/pages/data-processing/index.tsx` (live "Active
  now" section + mini run cards).
- Components under `resources/js/components/data-processing/` (run hero, stage
  lanes, live event stream, completion/failure summaries).
- Incremental event loading (append new, paginate older).
- Sticky mini-header, auto-follow, live/paused control, reduced-motion-safe
  transitions.

**Out**

- Retry/resume/cancel mechanics — surfaced here, but the flows are PIPE-07.
- The detail inspector tabs/raw view — PIPE-06.
- Notifications — PIPE-11.
- Clockify sync runs (SYNC-14) reuse these components later.

## 3. Data model

None beyond PIPE-01/02.

## 4. Backend

- **Route:** `GET /activity/{dataProcessingJob}` → `ActivityController@show`
  (`activity.show`), authorized with `DataProcessingJobPolicy::view`.
  Returns `run` (`PipelineRunResource`), `events` (tail page, ASC) and
  `eventsMeta` `{ oldest_sequence, latest_sequence, has_more_older }`.
- **Incremental events** on the same route via query params (Inertia partial
  reloads — no ad-hoc fetch):
  - `?after_sequence=N` with `only: ['events','run','eventsMeta']` → returns only
    events newer than N (append).
  - `?before_sequence=N` → returns the previous page of older events (prepend).
  - `ActivityController@show` branches on these params and returns the page with
    `only` filtered props.
- Controller stays thin: one service/query method per mode.
- Eager-load nothing heavy; the events repository paginates by `sequence`.

## 5. Frontend / UI

### 5a. Dedicated run page (`/activity/{job}`)

**Layout (top → bottom)**

```text
┌───────────────────────────────────────────────────────────────────────┐
│ ← Job activity            [Import · users]        [Pause live] [⋯]    │  sticky mini-header (on scroll)
├───────────────────────────────────────────────────────────────────────┤
│  Users import                                        ●  Running        │  run hero
│  Started 12m ago · by Ayesha · attempt 2/5                             │
│                                                                        │
│  ██████████████████████░░░░░░░░  70%                                  │
│  8,400 / 12,000 rows · 1,615 rows/min · ~2m left                       │
│  Stage: Writing records                                                │
│                                                                        │
│  ⓘ You can leave this page — the import keeps running in the background │
├───────────────────────────────────────────────────────────────────────┤
│  Stages                                    Fetch ✓ 0:21 · Write ●     │  stage lanes (TimelineTrack + rows)
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │ [Fetch  21s ✓][Write … running ▓▓▓▓▓░░░░]                        │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│  ▾ Write records     running · 8,400 / 12,000 · 12 issues              │
├───────────────────────────────────────────────────────────────────────┤
│  Live timeline            [All][Stages][Issues]  [🔍]   [Follow ▾]     │  event stream
│                                                                        │
│  ● 01:14:02  Dispatched to queue                                    21s │
│  │ 01:14:03  Started processing                                    0.3s│
│  │ 01:14:03  Stage started · Reading source file                      │
│  ├ 01:14:24  Stage completed · Reading source file · 12,000 rows   21s │
│  │ 01:14:24  Stage started · Writing records                          │
│  │ 01:15:12  Progress · 70% (8,400 / 12,000)                        12s │
│  │ 01:15:30  Warning · 12 rows skipped (duplicate email)              │
│  │ 01:15:41  ↻ Retry scheduled · attempt 2/5 · in 5s                  │
│  ▼ …                                                                   │
├───────────────────────────────────────────────────────────────────────┤
│  [Cancel]                                        [Download artifact]   │  action bar (status-aware)
└───────────────────────────────────────────────────────────────────────┘
```

**Run hero**

- Status dot + label (toned, `job-status-badge`), attempt chip when `> 1`.
- Big progress: `JobProgress` upgraded with ETA + throughput + elapsed
  (`formatDuration`/`formatThroughput`). Indeterminate → stage label + shimmer.
- The reassurance line (translated) shown while status is active.
- On `stale`, replace the reassurance with an amber "No recent progress" notice
  linking to PIPE-10.

**Stage lanes**

- `TimelineTrack` summarises the run as proportional segments.
- Below it, one expandable row per stage (`PipelineStageDTO`): status icon,
  label, duration, `processed/total`, mini progress bar, issue count. Expanding
  shows that stage's events filtered from the stream.

**Live event timeline** (`Timeline` from PIPE-04)

- Chronological top→bottom, newest at the bottom; **auto-follow** on by default
  (`aria-live="polite"`, but only stage/warning/error/terminal events are
  announced, never raw progress).
- Row: toned marker per `PipelineEventType`, title, description, relative time
  (absolute on hover), duration chip, attempt badge, optional correlation chip.
- Expandable context: pretty JSON of `context` (already redacted server-side),
  copy button.
- Filter chips (All / Stages / Issues), free-text search, and a "Follow" toggle;
  scrolling up shows a pinned **"Jump to latest"** button.
- New events animate in only when `pipeline_revision` changed (PIPE-03); no
  re-animation on unchanged polls.
- Older events load upward when scrolled to the top (`before_sequence`);
  the window is capped (e.g. 500 rows in DOM) to stay smooth.

**Terminal states**

- **Completed:** hero morphs to success (tone shifts, progress completes), shows
  counts (created/updated/failed/skipped), duration, artifact download, and
  CTAs ("View report" when applicable, "Back to job activity"). A subtle
  confetti-free success pulse honoring reduced motion.
- **Failed:** hero shows `failure.reason` title + `hint` + primary `action`
  (e.g. Retry / Reconnect Clockify), a collapsible raw `error_message` for users
  with `data-processing.manage`, and "Download failed rows" when an import
  report exists.
- **Cancelled:** muted hero with start-over affordance.

**Action bar**

- Buttons gated by `run.abilities` and permissions: Cancel (active), Retry
  (final failed/cancelled), Resume (if resumable), Download, Delete. Mechanics
  and confirmations are PIPE-07.

### 5b. `/activity` list upgrade

- Add an **"Active now"** section at the top: active runs rendered as mini run
  cards (name, entity, progress bar, stage, ETA, quick cancel/peek).
- Existing day grouping stays; each `JobRow` shows stage + ETA and a "Open" link
  to `activity.show`.
- The existing detail `Sheet` (PIPE-06 evolves it) remains the quick peek.
- Stat cards and filters stay; add a "Live/Paused" indicator fed by PIPE-03.

### States

- **Loading:** `TimelineSkeleton` + hero skeleton on first paint of a run page.
- **Empty events:** "Waiting for the first step…" placeholder.
- **Polling paused:** banner with Resume (existing pattern).
- **No active runs:** list shows history with a "Sync/Import" CTA.

### A11y & i18n

- Run page `<Head title>`; `<h1>` run name.
- Progress uses `role="progressbar"` with `aria-valuenow/min/max` and
  `aria-valuetext` (`8,400 of 12,000 rows`).
- The event region is `aria-live="polite"` with throttled announcements;
  provide a "Pause live" control that also pauses announcements.
- Keyboard: `/` focuses event search, `f` toggles follow, `Esc` closes
  state, `[`/`]` jump between stages.
- Every string (labels, empty states, hints) added to the five `lang/app/*.json`.

## 6. API / routes / props

- `GET /activity/{job}` (`activity.show`) →
  `{ run: PipelineRunResource, events: PipelineEventResource[], eventsMeta, options }`.
- Query params: `after_sequence`, `before_sequence` for incremental loads.
- No JSON endpoint; partial reloads only.

## 7. Acceptance criteria

- [x] A running job can be opened at its own URL and watched live.
- [x] Progress, ETA, throughput, elapsed and stage update without full reloads.
- [x] New events append automatically; older events load on scroll-up.
- [x] Auto-follow works, can be paused, and shows "Jump to latest".
- [x] Retry events are visible as distinct timeline entries.
- [x] Completed/failed/cancelled runs show the correct terminal summary.
- [x] `aria-live` announces stage/error/completion, not every progress tick.
- [x] Works in all four themes + high contrast; respects reduced motion.
- [x] `composer check` passes.

## 8. Tests

- **Feature** `tests/Feature/Pipeline/RunTimelinePageTest.php`:
  - `activity.show` renders for an owner and is forbidden otherwise;
  - returns the tail events and `eventsMeta`;
  - `?after_sequence=N` returns only newer events;
  - `?before_sequence=N` returns older events and `has_more_older`;
  - partial reload returns only `events`/`eventsMeta`;
  - terminal state exposes the mapped failure/abilities;
  - retry events render distinctly.
- **Feature** `tests/Feature/Export/ActivityControllerTest.php`: `/activity`
  carries `activeRuns` for the "Active now" section.
- **Unit** `tests/Unit/PipelineRunAggregatorTest.php`: `eventWindow` tail /
  append / prepend / empty-window math.
- **Frontend** `resources/js/components/data-processing/run-event-utils.test.ts`:
  progress collapsing, filtering/search, merge dedupe + DOM cap, tone mapping,
  and the announceable-event selector.
- Manual checklist (noted in the PR): auto-follow pin/unpin, "load older"
  scroll compensation, reduced-motion, and tone contrast across themes.

## 9. Notes & open questions

- **Frontend append strategy:** the run page polls the newest window
  (`only: ['run','events','eventsMeta','pipeline_revision']`) and merges each
  response into a client set keyed by `sequence` (`mergeEvents`), rather than
  polling with `after_sequence`. This avoids mutating the polled URL and closes
  the append gap risk because the tail window (100) dwarfs the per-poll deltas;
  `after_sequence`/`before_sequence` remain available on the route for
  SYNC/other consumers. Older history loads with a one-off
  `?before_sequence=<oldest>` partial reload.
- **Scroll:** prepends are compensated by a `useLayoutEffect` that adjusts
  `scrollTop` by the height delta, so loading history does not jump the
  viewport; the DOM window is capped at 500 events.
- **Progress noise:** consecutive `PROGRESS` events collapse to a single rolling
  row client-side (`collapseProgress`).
- **Announcements:** only stage/warning/error/terminal events reach the polite
  `LiveRegion`; raw progress never does.
- The sticky mini-header is a lightweight in-page bar; no height measurement was
  needed because the page scrolls natively rather than the header overlaying a
  scroll container.
- Retry/resume/cancel confirmations and backoff affordances remain PIPE-07.
