# PIPE-03 — Live polling transport

- **Status:** Done
- **Epic:** pipeline
- **Estimate:** M
- **Depends on:** PIPE-02
- **Blocks:** PIPE-05, PIPE-08
- **TDR:** §35–37

## 1. Why

Live progress is the heart of the pipeline experience, and it must be efficient:
the current `use-job-poll` (3 s `usePoll`) is per-component, ignores tab
visibility, has no backoff and cannot share a request between the page, the
sidebar badge and the header indicator. This spec defines one transport hook
that is visibility-aware, adaptive, error-backing-off and **shared** across all
subscribers, so a run can be watched from anywhere without hammering the server.

## 2. Scope

**In**

- `useLivePoll` — a single hook replacing ad-hoc `usePoll` usage.
- A module-level **shared registry** so N components with the same
  `url + only` share one timer and one in-flight request.
- Visibility-aware pausing, adaptive interval, exponential backoff on error.
- A server-side `pipeline_revision` value so clients can cheaply detect
  "nothing changed" and skip re-animating.
- Migrate `use-job-poll`, `use-active-jobs` to the shared transport.

**Out**

- WebSockets/broadcasting/SSE (defer; keep the transport behind the hook so it
  can be swapped in later — document the seam).
- Push notifications.

## 3. Data model

None. `pipeline_revision` is derived, not stored (e.g.
`max(updated_at)` of the relevant rows, or `count + max(id)`).

## 4. Backend

- Add a computed `pipeline_revision` to the Inertia props of polling pages
  (and/or a controller helper `PipelineRevision::forActiveRuns()`), included in
  the `only` list. It changes only when job state changes, so the client can
  skip work when unchanged.
- Ensure `/activity` supports Inertia **partial reloads** correctly:
  requesting `only: ['jobs','stats','activeJobs','pipeline_revision']` returns
  just those props (no full page recompute). Add a feature test for this.
- No new JSON endpoint: stay Inertia-native (frontend rule: no ad-hoc fetch).

## 5. Frontend / UI

**New files**

- `resources/js/hooks/use-live-poll.ts`
- `resources/js/hooks/use-live-poll-registry.ts` (internal shared timer store)
- Migrate `resources/js/hooks/use-job-poll.ts` and
  `resources/js/hooks/use-active-jobs.ts` to use it.

**API**

```ts
type LivePollStatus = 'paused' | 'idle' | 'polling' | 'error';

type LivePollOptions = {
    url: string;
    only: string[];
    enabled?: boolean;        // host-driven: poll only while there is active work
    interval?: number;        // active interval, default 3000
    idleInterval?: number;    // slow poll while idle, default 15000; 0 disables
    maxInterval?: number;     // backoff cap, default 60000
};

function useLivePoll(options: LivePollOptions): {
    live: boolean;
    status: LivePollStatus;
    isRefreshing: boolean;
    lastRefreshedAt: number | null;
    refresh: () => void;      // immediate reload
    pause: () => void;        // user pause
    resume: () => void;
};
```

**Behaviour**

- Only reload when the tab is visible; on `visibilitychange` to hidden, cancel
  the timer; on visible, refresh immediately then resume.
- Interval is adaptive: `interval` while `enabled`, `idleInterval` when not
  (if `> 0`), `maxInterval` after repeated errors (doubling each failure,
  resetting on success).
- Registry dedupes by `url + JSON.stringify(only)`; subscribers share one timer
  and one in-flight request. `lastRefreshedAt` is shared.
- `preserveState: true`, `preserveScroll: true`, `async: true`; never show the
  Inertia loading bar for background polls (`router.reload` with a `showProgress`
  guard / `onStart` that suppresses NProgress).
- If a poll response's `pipeline_revision` equals the previous one, mark
  `isRefreshing=false` and skip transition/animation hooks (PIPE-05 reads this).
- `pause()` is user-initiated (the existing "Live/Paused" toggle) and outranks
  `enabled`.

**Migration**

- `use-job-poll` becomes a thin wrapper returning the same `{ live, pause,
  resume }` so `pages/data-processing/index.tsx` needs no change initially.
- `use-active-jobs` (sidebar badge) subscribes to the same registry entry for
  `/activity` with `only: ['activeJobs','pipeline_revision']`.

### A11y & i18n

- The hook never announces; screens own `aria-live`. Provide `lastRefreshedAt`
  so a "Updated :time" line can be shown and announced politely.
- "Live"/"Paused" labels are translated keys.

## 6. API / routes / props

- No route changes; adds `pipeline_revision` to polled props.
- Partial reload contract documented in a feature test.

## 7. Acceptance criteria

- [x] Page + sidebar badge issue only **one** request per interval (verified in
      dev tools/network).
- [x] Polling stops when the tab is hidden and resumes on focus.
- [x] After errors, the interval backs off up to the cap and resets on success.
- [x] The Live/Paused toggle still works and is respected.
- [x] No Inertia progress bar flashes during background polls.
- [x] Unchanged responses do not trigger timeline re-animation.
- [x] `composer check` passes.

## 8. Tests

- Feature `tests/Feature/Pipeline/PartialReloadTest.php`:
  - `X-Inertia-Partial-Data: jobs,stats,activeJobs,pipeline_revision` returns only
    those props;
  - `pipeline_revision` changes when a job's state changes and is stable
    otherwise.
- Frontend `resources/js/hooks/use-live-poll.test.ts` (Vitest fake timers):
  shared timer/request across subscribers, exponential backoff + cap + reset,
  pause/resume, disabled idle polling, visibility pause/refresh, and
  revision-stamped `lastRefreshedAt`.

## 9. Notes & open questions

- `usePoll` from Inertia could be reused, but its fixed interval and lack of a
  shared registry are the reason for a manual scheduler; revisit if Inertia adds
  these.
- The registry is module-level and cleans up when its last subscriber unmounts
  (`resetLivePollRegistry()` exists for tests) to avoid stale timers.
- **Dedupe key:** entries are keyed by `url` alone with the union of the
  subscribers' `only` paths, rather than `url + JSON.stringify(only)`. This is
  what lets the page (`jobs,stats,activeJobs,pipeline_revision`) and the sidebar
  (`activeJobs,pipeline_revision`) resolve to the same entry and issue a single
  request; `lastRefreshedAt` stays shared across them.
- **Transport target:** `router.reload` always reloads the *current* page, so the
  `url` option is a logical registry key. The sidebar joins the entry only on the
  activity page (elsewhere it renders the server-shared `activeJobs`); global
  live status on every page is PIPE-08's cached `pipelineStatus`.
- **Failure counting:** cancelled/interrupted visits (e.g. a navigation racing a
  poll) do not increment the backoff counter.
- Future broadcast seam: `useLivePoll`/the registry are the only place that touch
  the network, so swapping `poll()` for a broadcast subscription is a local
  change.
