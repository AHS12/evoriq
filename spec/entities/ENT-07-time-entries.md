# ENT-07 — Time entries

- **Status:** Draft
- **Epic:** entities
- **Estimate:** L
- **Depends on:** ORG-01, ENT-00, ENT-02, ENT-04, ENT-05, ENT-06
- **Blocks:** ENT-08, ENT-10, ANA-01
- **TDR:** §10, §24, §26

## 1. Why

The time entry is the **central analytical fact**. Everything else is
dimensions around it. It is also the highest-volume, highest-cost entity and the
one most affected by the Free-plan budget — so it drives the planner and the
per-user fan-out.

## 2. Scope
**In:** `clockify_time_entries` from **per-user**
`GET /workspaces/{ws}/user/{userId}/time-entries?start&end&page&page-size&hydrated=true`.
**Out:** rates (ENT-08), custom-field values (ENT-10).

## 3. Data model
```text
clockify_time_entries
  id, organization_id, workspace_id, clockify_id,
  user_id, project_id null, task_id null,
  description null,
  start_at, end_at null, duration_seconds null,
  billable bool default false, type null,
  time_zone null, is_locked bool default false, is_in_progress bool default false,
  approval_status null,
  cost_amount null, cost_currency null,
  billable_amount null, billable_currency null,
  clockify_created_at null, clockify_updated_at null,
  created_at/updated_at/synced_at, raw_data, deleted_at
  unique (organization_id, workspace_id, clockify_id)
  index (organization_id, workspace_id, user_id, start_at)
  index (organization_id, workspace_id, project_id, start_at)
```

## 4. Backend
- `TimeEntrySyncHandler` (fact phase):
  - **Fan-out:** one job per active user × partition window (SYNC-03).
  - `start`/`end` from the job's range; `page`/`page-size`; `hydrated=true`.
  - **duration** derived from `timeInterval.start/end` (no documented `duration`
    field); handle null intervals (running/untracked entries).
  - map `tagIds[]` → `clockify_time_entry_tags` (ENT-06), delete–insert per entry.
  - resolve `userId`/`projectId`/`taskId` to internal ids (ENT-15).
- Deletion: soft (`deleted_at`) via ENT-13/SYNC-07.
- Idempotency: natural key (SYNC-08).
- Counters feed run/job totals (SYNC-04).

## 5. Frontend / UI
- None directly; powers analytics/report time facts and the pipeline volume
  numbers.

### A11y & i18n
- Duration rendering via FND-02.

## 6. API / routes / props
- Exposed via analytics (ANA) and reports (REP).

## 7. Acceptance criteria
- [ ] Entries sync per user across the selected range, paged and resumable.
- [ ] `duration_seconds` is derived and correct (incl. null-safe).
- [ ] Tags, project and task resolve to internal ids; missing parents handled.
- [ ] Re-running any page creates no duplicates.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `TimeEntrySyncHandlerTest` (`Http::fake()`): multi-page per user,
  duration derivation, null interval, tag joins, idempotency.
- **Feature** `TimeEntryImportTest`: two users × two pages → correct counts and
  no duplicates on re-run (mirrors the sync skill example).

## 9. Notes & open questions
- There is no workspace-wide date endpoint; confirm there is no bulk alternative
  beyond `POST /time-entries/batch` (which takes explicit ids only).
- `hydrated=true` cost: it also returns rates/custom fields — good for ENT-08/10
  but larger payloads; measure.
