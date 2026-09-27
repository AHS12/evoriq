# PIPE-03 — Live polling transport

- **Status:** Draft
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

- [ ] Page + sidebar badge issue only **one** request per interval (verified in
      dev tools/network).
- [ ] Polling stops when the tab is hidden and resumes on focus.
- [ ] After errors, the interval backs off up to the cap and resets on success.
- [ ] The Live/Paused toggle still works and is respected.
- [ ] No Inertia progress bar flashes during background polls.
- [ ] Unchanged responses do not trigger timeline re-animation.
- [ ] `composer check` passes.

## 8. Tests

- Feature `tests/Feature/Pipeline/PartialReloadTest.php`:
  - `X-Inertia-Partial-Data: jobs,stats,activeJobs,pipeline_revision` returns only
    those props;
  - `pipeline_revision` changes when a job's state changes and is stable
    otherwise.
- Hook logic (scheduling/backoff/visibility) is client-only; add unit tests
  under FND-09 (Vitest fake timers) when available. Until then, manual network
  verification is required and noted in the PR.

## 9. Notes & open questions

- `usePoll` from Inertia could be reused, but its fixed interval and lack of a
  shared registry are the reason for a manual scheduler; revisit if Inertia adds
  these.
- The registry is module-level and must clean up on unmount / route change to
  avoid stale timers.
- Document the future broadcast seam: a `LiveTransport` interface with a
  polling implementation now, broadcast later.
