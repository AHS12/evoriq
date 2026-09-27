# PIPE-09 — Historical import progress experience

- **Status:** Draft
- **Epic:** pipeline
- **Estimate:** L
- **Depends on:** PIPE-05, FND-03
- **Blocks:** ENT-14, DASH-05
- **TDR:** §10, §11, §34

## 1. Why

The first-run historical import is the product's make-or-break moment: it can
take a long time, it runs in the background, and the user must trust it. TDR §34
sketches the flow; this spec makes it real and beautiful — choose a range, see
an honest plan and estimate, start, watch a live timeline, and leave with
confidence. It is the pipeline experience applied to onboarding.

## 2. Scope

**In**

- A guided import flow UI: **Connect → Range → Review → Import**.
- Range step using FND-03 with historical presets (**Last year / Last 2 years /
  Last 5 years / Custom**), capped at **5 years** (`DEC-003`).
- A **workspace/volume inspection** call and a **plan preview** (phases,
  partitions, estimated requests and duration).
- Starting the import and handing off to the live run experience (PIPE-05).
- A completion summary with a clear next step (dashboard/reports).
- "You can leave this page" reassurance and background continuation.

**Out**

- The actual fetching/upsert engine (ENT-14) and planner math (SYNC-03).
- Connection credential entry (CONN-04) — step 1 reuses it.
- Incremental sync (SYNC-06/12).

## 3. Data model

- No new tables in this spec; it consumes `ImportPlan` data from SYNC-03 and
  creates runs via ENT-14.

## 4. Backend

- **Routes** (auth, verified, permission `import.create` or `connection.manage`):
  - `GET /import` → `ImportWizardController@index` (`import.index`) — wizard
    shell + current connection/range options.
  - `POST /import/inspect` → `@inspect` (`import.inspect`) — calls the
    workspace inspect service (rate-limited `ClockifyClient`) and returns an
    `ImportPlanResource` (volumes, partitions, estimates). No writes.
  - `POST /import` → `@store` (`import.store`) — validates the range, asks
    `ClockifySyncPlanner` for the plan, dispatches the import run(s), and
    redirects to `activity.show` (or `activity.index` for multi-run plans).
- **Service:** `Services\Import\ImportWizardService` orchestrates
  inspect/plan/dispatch; delegates to the sync/planner services. No queries or
  HTTP in the controller.
- **Resource:** `ImportPlanResource`:
  `{ range: {start,end}, volumes: {...}, phases: [...], partitions: [...],
     estimated_requests, estimated_duration_seconds, entity_count }`.
- **Guards:** refuse `inspect`/`store` without a connected workspace; validate
  the range against workspace bounds (from ENT-01/CONN-03).
- **Audit:** record an `IMPORT_STARTED`/domain event with the chosen range.

## 5. Frontend / UI

**Files**

```text
resources/js/pages/import/index.tsx                 # wizard shell + steps
resources/js/components/import/range-step.tsx       # preset cards + custom
resources/js/components/import/inspect-summary.tsx  # discovered volumes
resources/js/components/import/plan-review.tsx      # phases/partitions/estimates
resources/js/components/import/import-run-panel.tsx # live progress (embeds PIPE-05 hero)
resources/js/components/import/import-complete.tsx  # completion summary + CTAs
resources/js/types/import.ts
resources/js/lib/schemas/import.ts                  # Zod for range step
```

**Steps**

1. **Connect** — if no active connection, render CONN-04's form inline; else
   show the connected workspace (name, currency, timezone) and continue.
2. **Range** — FND-03 picker plus preset cards:
   - "Last year", "Last 2 years", "Last 5 years" (max), "Custom range" — each
     with a short human description. No "all history" option (`DEC-003`).
   - Selecting a preset triggers `import.inspect` (debounced) to show discovered
     volumes inline ("~1.28M time entries, 18 users, 27 projects").
   - The `ClockifyClient` inspect is rate-limited; show a subtle "Checking your
     workspace…" state and never block the UI.
3. **Review** — `plan-review.tsx`:
   - Summary cards: date range, entities, estimated API requests, estimated
     duration ("about 25 minutes"), and a note that it runs in the background.
   - A phase list (Dimensions → Facts → Derive) with what each includes and a
     rough share of the work.
   - Honest caveats ("Estimates are approximate; Clockify rate limits apply").
   - Primary "Start import"; secondary "Back".
4. **Import** — on start, dispatch and show `import-run-panel.tsx`, embedding
   the PIPE-05 run hero + live timeline for the primary run (and a compact list
   if the plan produced several runs).
   - Prominent reassurance banner: "You can leave this page — the import keeps
     running in the background." with a "Go to dashboard" button.
   - Progress milestones: "Imported 1,284,921 records · Currently: March 2024"
     (TDR §34).
5. **Complete** — `import-complete.tsx`: success hero, totals, duration, and
   CTAs ("Open dashboard", "View reports", "Run another import").

**States**

- No connection → step 1 form.
- Range too large relative to budget → review shows a warning and a suggested
  smaller range; starting anyway is allowed but explained.
- Inspect fails (API/rate limit) → retry affordance; allow "Start without
  estimate" with a caveat.
- Interrupted/duplicate import → detect an active import and offer to resume/open
  it instead of starting another.

### A11y & i18n

- Wizard is a labelled stepper (`aria-current="step"`), each step an `<h2>`
  section; focus moves to the new step heading on transition.
- Progress/announcements follow PIPE-05's rules.
- All copy (descriptions, caveats, CTAs) translated in the five
  `lang/app/*.json`.

## 6. API / routes / props

- `import.index` props: `connection` (sanitized), `rangeOptions`,
  `workspaceBounds`, `activeImport` (if any).
- `import.inspect` → `ImportPlanResource` (JSON).
- `import.store` → redirect to `activity.show`.

## 7. Acceptance criteria

- [ ] A user can go from no data to watching a live historical import.
- [ ] Presets and custom range both work and show discovered volumes.
- [ ] Review shows a believable plan with request/duration estimates.
- [ ] Starting hands off to the live run page; the import continues if the user
      navigates away.
- [ ] Completion summary shows totals and working CTAs.
- [ ] A second concurrent import is prevented; an active one is surfaced.
- [ ] `composer check` passes.

## 8. Tests

- **Feature** `tests/Feature/Import/ImportWizardTest.php`:
  - `import.index` requires a connection and renders;
  - `import.inspect` with `Http::fake()` returns plan volumes/estimates and is
    rate-limit aware;
  - `import.store` validates the range, dispatches runs, and redirects;
  - starting while an import is active is rejected with a helpful message.
- **Unit:** inspect/plan mapping and guard logic (delegated services mocked).

## 9. Notes & open questions

- Duration estimate heuristic: needs SYNC-03 to provide a baseline; until then
  show request counts only and label duration "unknown".
- **Free-plan reality:** at 30 requests/hour/workspace a multi-year import takes
  hours. The review step must set this expectation honestly and the run must be
  fully resumable. On paid plans the estimate collapses to minutes. This is the
  single most important reason the PIPE epic exists (`DEC-006`).
- Time entries are fetched **per user** (`ENT-07`), so the estimate scales with
  users × pages, not just date range.
- Decide whether the wizard lives under `/import` or `/onboarding/import`;
  recommendation: `/import` so it can be re-run by existing users.
- Multi-run plans: define how many runs are created (one per phase vs one
  umbrella run with child jobs). Recommendation: one umbrella `SyncRun` with
  child `SyncJob`s (SYNC-09), surfaced as a single progress experience.
