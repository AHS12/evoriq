# ENT-05 — Tasks

- **Status:** Done
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00, ENT-04
- **Blocks:** ENT-07, ANA-* (task dimension)
- **TDR:** §25.8

## 1. Why

Tasks are a real reporting dimension, not display metadata: they power
task-level breakdowns and are attached to time entries.

## 2. Scope
**In:** `clockify_tasks` from `GET /projects/{projectId}/tasks`.
**Out:** task CRUD.

## 3. Data model
```text
clockify_tasks
  id, organization_id, workspace_id, project_id, clockify_id,
  name, status null, assignee_user_id null,
  billable bool default false, estimated_hours null,
  billable_rate_amount null, billable_rate_currency null,
  cost_rate_amount null, cost_rate_currency null,
  completed_at null, created_at/updated_at/synced_at, raw_data, deleted_at
  unique (organization_id, workspace_id, clockify_id)
```

## 4. Backend
- `TaskSyncHandler`: iterate projects (reference phase) → paginate tasks per
  project; map `assigneeId(s)`, `status`, `duration` (ISO-8601 → seconds/hours).
- Map `hourlyRate`/`costRate` (`{amount, since}`) when present.
- Deletion: soft; project deletion cascades via ENT-13 rules.

## 5. Frontend / UI
- Task dimension in reports (REP-05) and drill-downs (REP-06).

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via report resources.

## 7. Acceptance criteria
- [x] All tasks per project sync with status and assignee.
- [x] ISO-8601 duration parsed correctly.
- [x] Re-sync idempotent; project parent resolved.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `TaskSyncHandlerTest` (`Http::fake()`): nested per-project paging,
  duration parsing, assignee mapping.

## 9. Notes & open questions
- Tasks are nested by project, so task sync cost scales with project count;
  planner accounts for this.
- **Implemented notes:**
  - `clockify_tasks` (soft-deletable, project FK required, assignee FK) +
    `ClockifyTask` model/factory/casts.
  - Nested pagination: the runner's flat `page` counter does not map onto
    (project × page), so `TaskSyncHandler::fetchPage()` overrides the base
    single-page fetch — it iterates the workspace's already-synced projects
    (via `ClockifyProjectRepositoryInterface`) and paginates the tasks endpoint
    per project (`ClockifyClient::paginate`), aggregating the whole reference
    set on the first call (subsequent calls complete the job). The trade-off is
    that a task snapshot is written in one page-transaction rather than being
    resumable mid-snapshot; acceptable for a reference dimension.
  - `AbstractSyncHandler::endpoint()` is now a concrete method that throws by
    default, so handlers that override `fetchPage()` (this nested case) are not
    forced to declare an endpoint.
  - `TaskSyncRepository` resolves reserved `project_clockify_id` /
    `assignee_clockify_id` keys to internal ids in a batch (targeted ENT-15);
    tasks with an unresolvable project are skipped (the FK is required). The
    handler also maps `hourlyRate`/`costRate` (workspace-currency fallback).
  - `estimated_hours` is parsed from `estimate`/`duration` ISO-8601 (or a
    numeric `estimatedHours`) via `Iso8601Duration`.
