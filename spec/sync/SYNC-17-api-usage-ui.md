# SYNC-17 — API usage & budget UI

- **Status:** Done
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-02, PIPE-08
- **Blocks:** —
- **TDR:** §13, §14, §37

## 1. Why

"Make API consumption transparent" is a stated goal, and on Free plans the
budget is tiny — users must know the remaining requests and when they reset.
This is the visible face of `SYNC-02`.

## 2. Scope

**In**
- A navbar indicator (`API 41/50` or `24 remaining`) and a popover with
  used/remaining/limit/reset/last request.
- Honest presentation: the window is **hourly on Free**, **per-second on paid**;
  never imply a fixed limit.
- Live refresh tied to the pipeline polling (PIPE-03).

**Out**
- Accounting (SYNC-02); freshness (SYNC-18).

## 3. Data model
- Reads `clockify_api_usage` via `ApiUsageService::snapshot()`.

## 4. Backend
- **Resource** `ApiUsageResource`: `{ window_type, used, limit, remaining,
  resets_at, resets_in, last_request_at, plan }`.
- **Prop:** include `apiUsage` in the shared `pipelineStatus` (PIPE-08) or a
  dedicated shared prop; polled via partial reload.
- **Route (optional):** `GET /api/usage` (`api-usage.show`) for the popover to
  refresh independently.

## 5. Frontend / UI

**Files**
```text
resources/js/components/pipeline/api-usage-indicator.tsx
resources/js/components/pipeline/api-usage-popover.tsx
```
- Indicator: compact `API {remaining}/{limit}` (or `{used}/{limit}` per plan),
  toned (normal/low/empty).
- Popover: table of Used / Remaining / Limit; "Resets in 42 min"; "Last request
  2:14 PM"; plan badge; link to Sync activity (PIPE).
- States: healthy, low (< 20%), exhausted (with reset countdown), unknown (no
  connection yet).
- On Free plans, when exhausted, show the reset time prominently and link to
  Sync Now (SYNC-12) which explains deferral.

### A11y & i18n
- Indicator has an accessible label ("Clockify API: 41 of 50 requests
  remaining"); popover keyboard-operable; all strings translated.

## 6. API / routes / props
- Shared `apiUsage` prop + optional `api-usage.show`.

## 7. Acceptance criteria
- [x] Indicator shows accurate used/remaining for the active window.
- [x] Free vs paid wording differs correctly; no fixed-limit implication.
- [x] Reset countdown is accurate and updates live.
- [x] Exhausted state is clear and links to available actions.
- [x] `composer check` passes.

## 8. Tests
- **Feature** `ApiUsagePropTest`: prop shape by plan; reset math.
- **Unit** projection from `ApiUsageService::snapshot()`.

## 9. Notes & open questions
- Decide whether per-second (paid) usage is meaningful to show (it resets
  instantly); for paid, prefer a rate meter rather than a remaining count.

### Implemented notes

- Backend: a lazy shared `apiUsage` prop in `HandleInertiaRequests`, gated by
  `ClockifyConnectionPolicy::viewAny`, projecting `ApiUsageService::snapshot()`
  through `ApiUsageResource` (null with no connection / no permission). The
  existing `GET /api/usage` endpoint remains for direct refresh.
- Frontend: `ApiUsageIndicator` (navbar + sidebar header) shows
  `API {remaining}/{limit}` on Free and `API {limit}/s` on paid, toned
  normal/low/exhausted, with an accessible label. `ApiUsagePopover` renders
  Used/Remaining/Hourly-limit on Free and an honest per-second sentence on paid,
  a **1s-ticking reset countdown**, last request, plan badge and a link to sync
  activity. Hidden entirely when no connection is configured.
- Freshness: `useApiUsage` polls the shared prop every 15s (idle) via the
  PIPE-03 transport, so the countdown and remaining count stay current; the poll
  is disabled when there is no connection.
- Paid plans show a rate label rather than a remaining count (a per-second
  window resets instantly and a remaining count would be meaningless).
