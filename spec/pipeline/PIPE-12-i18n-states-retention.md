# PIPE-12 — Pipeline i18n, states & retention

- **Status:** Done
- **Epic:** pipeline
- **Estimate:** M
- **Depends on:** PIPE-01 … PIPE-11
- **Blocks:** —
- **TDR:** §34–38, §42

## 1. Why

A flagship UI only holds together if every string is translatable, every empty
and error path is designed, and the event stream does not quietly grow without
bound. This spec closes the pipeline epic: it extracts all copy into the five
locale dictionaries, standardizes loading/empty/error states, and adds retention
so observability does not become a database liability.

## 2. Scope

**In**

- Every new pipeline string added to **all five** `lang/app/*.json` files
  (`en`, `bn`, `fr`, `de`, `es`), following the Bangla transliteration rule.
- Shared loading/empty/error states applied across the pipeline surfaces
  (list, run page, inspector tabs, wizard, indicator).
- Event retention: a scheduled prune command, coalescing caps, and cascade
  deletion of events when a run is deleted.
- A small pipeline glossary for translators (types, stages, failure reasons).
- Verification that `TranslationParityTest` passes.

**Out**

- Introducing a frontend test runner (FND-09) — but hooks into it where useful.
- Rewriting unrelated existing strings.

## 3. Data model

- No new tables. Optionally add `(run_type, run_id)` delete-cascade handling via
  service logic (no FK).
- Consider an index supporting prune: `(occurred_at)` on `pipeline_events`
  (added in PIPE-01).

## 4. Backend

- **Command:** `App\Console\Commands\Pipeline\PrunePipelineEventsCommand`
  (`pipeline:prune-events`) — deletes events older than
  `config('pipeline.event_retention_days')`; scheduled daily in
  `routes/console.php`. Reports deleted count.
- **Cascade:** `DataProcessingJobService::delete()` deletes the run's events
  (`PipelineEventRepository::deleteForRun`) in the same transaction. Update the
  scheduled `data-processing:cleanup-completed` to rely on this.
- **Caps:** enforce a max events per run (e.g. `pipeline.max_events_per_run`,
  default 5,000) — when exceeded, the recorder drops `DEBUG`/`PROGRESS` events
  first (never stage/error/terminal events) and records a single warning.
- **Glossary:** add a `docs`/spec note mapping `PipelineEventType`,
  `PipelineEventLevel` and `PipelineFailureReason` labels for translators.

## 5. Frontend / UI

**States inventory**

| Surface                 | Loading                    | Empty                                 | Error                              |
| ----------------------- | -------------------------- | ------------------------------------- | ---------------------------------- |
| `/activity` list        | list skeleton              | "Nothing running" + import CTA        | inline error + retry               |
| Run page                | hero + timeline skeleton   | "Waiting for the first step…"         | run failed banner (PIPE-05)        |
| Event stream            | row skeletons              | "No events yet"                       | "Couldn't load events" + retry     |
| Issues tab              | table skeleton             | "No issues — every row imported"      | inline error                       |
| Artifacts tab           | card skeletons             | "No files generated"                  | inline error                       |
| Advanced tab            | table skeleton             | "No events"                           | permission-hidden                  |
| Indicator popover       | pill skeleton              | hidden when idle                      | "Couldn't refresh" + retry         |
| Import wizard steps     | step skeletons             | no-connection → CONN step             | inspect failure + retry            |

- Use FND-06 primitives (`EmptyState`, `Skeleton`, `ErrorState`, `InlineAlert`).
- Error text is user-friendly; technical detail only in Advanced.

**i18n keys (non-exhaustive; extract during implementation)**

- Statuses/stages/types labels and descriptions (render PHP enum labels via
  `t()`).
- Failure reason labels, hints, and actions (`retry`, `reconnect`, `wait`).
- Reassurance: "You can leave this page — the import keeps running in the
  background."
- Timeline controls: "All", "Stages", "Issues", "Follow", "Jump to latest",
  "Updated :time", "Live", "Paused".
- States/counters: ":processed of :total rows", "~:eta left", ":count rows/min".
- Confirmations from PIPE-07.
- Indicator: "Background jobs", ":count running", "recent failures".

**Glossary note**

- Keep a short section in this spec or `docs/` that explains, for translators:
  `stage` vs `type`, `attempt`, `throughput`, `ETA`, `stale`, and the Bangla
  transliteration choices (স্ট্যাটাস, ইমপোর্ট, এক্সপোর্ট, রোলস).

### A11y & i18n

- English source string is the key; use `:param` interpolation, never
  concatenation (`AGENTS.md` §8.11).
- Module-scope helpers receive `t` (e.g. `job-utils.ts` pattern).
- Verify no untranslated string remains by grepping new components for bare
  JSX text in a review pass.

## 6. API / routes / props

- No new routes beyond the prune command; strings live in dictionaries.

## 7. Acceptance criteria

- [x] `TranslationParityTest` passes with all pipeline keys in five locales.
- [x] No user-visible pipeline string is hard-coded English.
- [x] All surfaces implement loading/empty/error states per the table.
- [x] `pipeline:prune-events` deletes old events and is scheduled.
- [x] Deleting a run deletes its events.
- [x] Event caps prevent unbounded per-run growth without losing important
      events.
- [x] `composer check` passes.

## 8. Tests

- **Feature** `tests/Feature/Pipeline/PrunePipelineEventsCommandTest.php`:
  old events removed, recent kept, terminal events respected.
- **Feature** `tests/Feature/Pipeline/PipelineEventCapTest.php`: exceeding the
  cap drops progress/debug first and keeps terminal events + a warning.
- **Unit/Feature:** run deletion cascades events.
- Existing `tests/Unit/TranslationParityTest.php` covers dictionary parity.

## 9. Notes & open questions

- Retention default (30 days) vs storage growth: measure event volume per large
  import before fixing the default; make it configurable.
- Consider archiving very old completed runs' events to a cold file instead of
  deleting (defer).
- The cap interacts with PIPE-01 coalescing; document precedence clearly.

### Implemented notes

- **Retention:** `pipeline:prune-events` (scheduled daily) deletes events older
  than `pipeline.event_retention_days` (30); `--days` and `--dry-run` are
  supported. Repository gained `countBefore()` / `deleteForRun()` /
  `countForRun()`.
- **Cascade:** `DataProcessingJobService::delete()` deletes the run's events
  inside the same transaction (no FK on `run_id`).
- **Cap:** `pipeline.max_events_per_run` (default 5000). Once reached, the
  recorder drops only progress/debug/info events and writes a single
  `WARNING` (`context.event_cap`) — stage, warning, error and terminal events
  always persist. Precedence: coalescing (PIPE-01) runs first, then the cap.
- **i18n:** all pipeline surfaces (list, run page, inspector, wizard,
  indicator, PIPE-10 developer page) and every pipeline enum label
  (`PipelineEventType`, `PipelineEventLevel`, `PipelineFailureReason`,
  `DataProcessingJobStatus`, `PipelineRunType`, sync enums) are present in all
  five `lang/app/*.json`; `TranslationParityTest` passes.
- **States:** loading/empty/error states are provided by the existing FND-06
  primitives across the run page, event stream, inspector tabs, indicator and
  wizard (inspect error + retry).

### Translator glossary

- `type` — the run kind (import/export/report/sync); `stage` — a named phase
  within a run (e.g. `read`, `write`, an entity name).
- `attempt` — the number of times a run has been picked up (retries included).
- `throughput` — records processed per minute; `ETA` — estimated time remaining;
  `stale` — a running job whose heartbeat is older than the threshold.
- Bangla transliteration choices: স্ট্যাটাস, ইমপোর্ট, এক্সপোর্ট, রোলস, জব, কিউ,
  পাইপলাইন, ড্যাশবোর্ড.
