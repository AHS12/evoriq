# SYNC-03 — Sync planner

- **Status:** Draft
- **Epic:** sync
- **Estimate:** L
- **Depends on:** SYNC-01, SYNC-02, CONN-03
- **Blocks:** SYNC-04, SYNC-09, PIPE-09, ENT-14
- **TDR:** §10, §11, §17

## 1. Why

This is the "system intelligently decides and sets up the full import pipeline"
step. Given a workspace, a desired entity set and a date frame (≤ 5 years), the
planner turns that into ordered, budget-aware, resumable jobs. It must never
assume "one month = one request" and must respect the Free hourly budget.

## 2. Scope

**In**
- `ClockifySyncPlanner`: range + scope → `ImportPlan` (phases, jobs, partitions,
  estimates).
- Phase ordering: **reference dimensions → facts → reconciliation baseline**.
- Dynamic partitioning with a **31-day default** (per `DEC-009`), shrinking when
  a partition is too voluminous.
- Per-user time-entry fan-out (`ENT-07`).
- Request/duration estimates from volume + the connection's budget profile.
- Range clamping to `clockify_max_history_years` (CONN-09).

**Out**
- Executing jobs (SYNC-04/SYNC-09); the review UI (PIPE-09).

## 3. Data model
- `ImportPlan` value object (serialized into `clockify_sync_runs.plan`).
- Volume inputs come from a lightweight inspect (counts via crafted 1-item
  queries, or user count) at plan time.

## 4. Backend
- **DTOs** `App\DTOs\Sync\ImportPlan`, `ImportPlanPhase`, `ImportPlanJob`,
  `ImportPlanEstimate`.
- **Service** `Services\Sync\ClockifySyncPlanner::plan(PlanRequest): ImportPlan`:
  1. Clamp `range_end` to now and `range_start` to `max(years, earliest)`.
  2. Resolve entity set for `mode` (`initial` = all MVP entities; `incremental` =
     changes; `reconciliation` = rolling window).
  3. For **reference** entities: one job per entity (full snapshot, paged).
  4. For **facts** (`TIME_ENTRY`): one job **per active user** × partition
     windows; rates/CFVs follow their entry partitions.
  5. Partition size: start at 31 days; if the inspect shows high volume, shrink
     (e.g. 14/7 days) — never below the point where a page is exceeded.
  6. Estimate `requests = Σ ceil(volume / page_size) + 1` per job;
     `duration = requests / budget` (hourly → hours).
  7. Order jobs by priority (reference high, facts normal, reconciliation low).
- **Inspect** `WorkspaceInspector::volumes(connection, workspace, range)`: uses
  minimal `page-size=1` probes where a count is needed; cached.
- **Resource** `ImportPlanResource` for PIPE-09 review.
- **Determinism:** same inputs → same plan (stable ordering, no randomness).

## 5. Frontend / UI
- Consumed by PIPE-09 review step: phase list, partitions count, estimated
  requests and duration, honest Free-plan caveats.

### A11y & i18n
- Estimates formatted client-side (FND-02); labels translated.

## 6. API / routes / props
- `POST /import/inspect` → `ImportPlanResource` (PIPE-09).

## 7. Acceptance criteria
- [ ] A 5-year request produces a plan capped at 5 years with 31-day partitions
      by default.
- [ ] Time entries fan out per user; reference entities load first.
- [ ] Estimates include request counts and a duration based on the real budget.
- [ ] Plan is deterministic and serializable.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ClockifySyncPlannerTest`: range clamping, phase ordering, per-user
  fan-out, budget-based duration on Free vs paid, partition shrink, determinism.

## 9. Notes & open questions
- Volume inspection costs requests (counts against the budget); make it optional
  and cacheable, and allow "start without estimate" (PIPE-09).
- Decide exact thresholds for shrinking partitions empirically (`SPIKE-06`
  adjacent).
