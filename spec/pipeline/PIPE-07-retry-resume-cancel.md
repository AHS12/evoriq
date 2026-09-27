# PIPE-07 — Retry, resume, cancel & backoff UX

- **Status:** Draft
- **Epic:** pipeline
- **Estimate:** M
- **Depends on:** PIPE-05
- **Blocks:** PIPE-09, PIPE-10
- **TDR:** §17, §23, §38, §41

## 1. Why

Long jobs fail — that is normal and must feel safe. The product promise
(TDR §41) is idempotent, retryable, resumable work. Today there are endpoints
for cancel/retry/duplicate but no clear UX, no distinction between *retry from
scratch* and *resume*, no backoff visibility and no bulk recovery. This spec
makes recovery a first-class, confidence-inspiring experience: the user always
knows what will happen, whether it is safe, and what is already done.

## 2. Scope

**In**

- A clear action model: **Cancel**, **Retry**, **Resume**, **Duplicate**,
  **Delete**, **Download**, driven by `PipelineRunResource.abilities`.
- Status-aware action bar + row actions with the right primary action.
- Confirmations with real consequences (not generic "Are you sure?").
- Retry/backoff display in the timeline (countdown, attempt `N/M`).
- Failure-reason-driven primary action (`retry` / `reconnect` / `wait`).
- Bulk "Retry all failed" on the failed view.
- Idempotency/safety guarantees documented and enforced server-side.

**Out**

- Queue/backoff configuration changes (stays as `QueueRegistry`).
- Clockify sync checkpoint resume mechanics (SYNC-04) — this spec defines the
  *ability* and UX; sync fills the data.
- Scheduled retries beyond what the queue already provides.

## 3. Data model

- Reuses PIPE-02 columns: `attempt`, `next_retry_at`, `failure_reason`.
- `retry()` clears `failure_reason`, `next_retry_at`, `errors`,
  `completed_at`, `cancel_requested_at`, counters; keeps `attempt` history in
  events (each retry emits `RETRY_SCHEDULED` → `RETRY_STARTED`).

## 4. Backend

- **Service (`DataProcessingJobService`)**:
  - `cancel()` — keep behaviour; emit a `WARNING` ("Cancellation requested") when
    cooperative so the UI can show "stopping after the current step".
  - `retry()` — only final jobs; reset as above; `attempt` starts a new segment
    and `max_attempts` is surfaced from the run context; emit `RETRY_SCHEDULED`.
  - `resume()` — new; for runs that expose a checkpoint/resume token
    (`resume_token`/checkpoint). For `DataProcessingJob` imports, "resume" re-runs
    idempotently and skips already-imported rows; for sync runs (SYNC-04) it
    continues from the last page. Expose `abilities.resume` only when a resume
    strategy exists; otherwise omit.
  - `duplicate()` — keep; allow pre-filled parameter editing from the UI.
  - `retryFailed(array $ids)` — bulk; skips non-final and non-owned rows, returns
    `{ queued, skipped }`.
  - All mutations are transactional and record audit events (already done) plus
    pipeline events.
- **Policy:** all actions require `data-processing.manage`; bulk requires the
  same; delete requires `data-processing.delete`.
- **Safety:**
  - Retry/resume are idempotent: re-running an import must not create duplicates
    (upsert by natural key / existing `UserImport` semantics).
  - Reject retry on active jobs (already) with a 409-style validation error.
  - Guard against concurrent duplicate runs of the same entity+parameters within
    a short window? Add a soft warning in `abilities` (`duplicate_soon: true`),
    not a hard block.
- **Routes:** `activity.retry`, `activity.cancel`, `activity.duplicate` exist;
  add `activity.resume`, `activity.retry-failed`.

## 5. Frontend / UI

**Action model (status-aware)**

| Status               | Primary          | Secondary (overflow)                    |
| -------------------- | ---------------- | --------------------------------------- |
| `pending`            | Cancel           | Delete                                  |
| `processing`         | Cancel (confirm) | Delete (confirm, warns partial data)     |
| `completed`          | Download         | Duplicate, Delete                       |
| `failed`             | Retry / reconnect| Resume (if available), Duplicate, Delete, Download failed rows |
| `cancelled`          | Retry            | Resume (if available), Duplicate, Delete |

- Primary actions render as prominent buttons; the rest collapse into the
  existing `job-actions` dropdown.
- `failure.action` chooses the failed-state primary: `retry` → "Retry",
  `reconnect` → "Reconnect Clockify" (link to connection), `wait` →
  "Retry later" (disabled with tooltip until `next_retry_at`).

**Confirmations** (reuse `ConfirmDialog`)

- **Cancel processing:** "Stop this run? It will finish the current step and
  stop. Partially imported rows are kept and a re-run is safe (no duplicates)."
- **Delete active:** "Delete while running? The run stops and is removed."
- **Retry:** no dialog; optimistic toast "Retrying…".
- **Bulk retry:** "Retry :count failed runs?" with a summary of reasons.

**Backoff & retry visuals**

- `retryScheduled` timeline entry shows "Retrying in :count (attempt :n/:max)"
  with a live, second-by-second countdown (client-computed from `next_retry_at`).
- The run hero shows an attempt chip (`attempt 2/5`) and, when waiting,
  a "Retrying soon" state instead of a dead progress bar.
- HTTP-level retries (ClockifyClient) appear as `retryScheduled` warning entries
  with the reason (`rate_limited`, `network`).

**Optimistic behaviour**

- After cancel/retry/resume, immediately reflect the intended state locally
  (button disabled + inline pending), then let PIPE-03 polling confirm. On
  server validation failure, show an error toast and revert.
- Bulk retry shows a progress toast ("0 of 5 queued…" → "5 queued").

**Failed view**

- The existing Failed stat card filters the list; add a toolbar action
  **"Retry all failed"** when the filtered set is non-empty and the user can
  manage. Selection checkboxes allow retrying a subset.

### States

- Disabled action with tooltip when the user lacks permission or the run is in
  a transitional state.
- Idempotency reassurance text on import retries.

### A11y & i18n

- Countdowns are visually live but announced only at "retrying now".
- Confirm dialogs trap focus (Radix) and have descriptive titles/descriptions.
- All consequence strings, reasons and hints translated in the five
  `lang/app/*.json`.

## 6. API / routes / props

- `POST /activity/{job}/cancel`, `/retry`, `/duplicate` (existing).
- `POST /activity/{job}/resume` (new).
- `POST /activity/retry-failed` (new, bulk) → `{ queued, skipped }`.
- Abilities surfaced in `PipelineRunResource.abilities` incl. `resume` and
  `duplicate_soon`.

## 7. Acceptance criteria

- [ ] Cancel on a processing run stops at the next safe boundary and reports
      "cancelled" with a clear explanation.
- [ ] Retry on a failed run re-runs safely and is idempotent (no duplicates).
- [ ] Resume continues from the checkpoint where supported, and is hidden
      otherwise.
- [ ] Failure reasons drive the correct primary action (retry/reconnect/wait).
- [ ] Backoff countdown is visible and accurate.
- [ ] Bulk retry queues owned final runs and skips others, with a result toast.
- [ ] All confirmations state real consequences.
- [ ] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/DataProcessingJobRetryTest.php`:
  - retry resets state and is rejected on active jobs;
  - `abilities.resume` present only when a resume strategy exists;
  - failure-action mapping (`retry`/`reconnect`/`wait`).
- **Feature** `tests/Feature/Pipeline/RunRecoveryTest.php`:
  - cancel/retry/resume endpoints authorize correctly and emit the expected
    events;
  - bulk retry queues only owned final runs and returns counts;
  - retry of an import is idempotent against `tests/Mock/UploadMockData`
    fixtures.
- Ensure existing `DataProcessingJobFeatureTest` still passes.

## 9. Notes & open questions

- Do we need a true chunk-level resume for CSV imports (a checkpoint every N
  rows)? For MVP, idempotent re-run is acceptable; sync (SYNC-04) has real
  checkpoints. Flag as a follow-up if import re-runs get expensive.
- Decide whether `duplicate_soon` should warn or silently allow; recommendation:
  warn only.
- `retry-failed` should be rate-limited/queued to avoid mass re-dispatch; queue
  it through the `default` channel if the set is large.
