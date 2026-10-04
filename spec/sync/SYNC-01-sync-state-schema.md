# SYNC-01 — Sync state schema

- **Status:** Done
- **Epic:** sync
- **Estimate:** L
- **Depends on:** ORG-01, CONN-01
- **Blocks:** SYNC-02 … SYNC-20, all ENT-*
- **TDR:** §13, §20, §23, §25.24–25.27

## 1. Why

Everything about the pulling flow — planning, resumability, progress, deletion
correctness, recovery — lives in a handful of sync-infrastructure tables. This
spec defines them once, org-scoped, so runs and jobs can be paused, resumed and
audited, and so raw upstream data can rebuild the normalized model.

## 2. Scope

**In**
- Tables: `clockify_sync_runs`, `clockify_sync_jobs`, `clockify_api_usage`,
  `clockify_entity_changes`, `clockify_deleted_entities`, `clockify_raw_records`.
- Enums for trigger, run/job status, entity type, change type.
- Indexes and org scoping.

**Out**
- The logic that uses them (SYNC-02 … SYNC-20).

## 3. Data model

All tables carry `organization_id` (ORG-01), `workspace_id` (internal FK or
Clockify id per convention), and timestamps.

```text
clockify_sync_runs
  id, organization_id, connection_id, workspace_id
  trigger            # SyncTrigger
  mode               # initial|incremental|reconciliation
  priority           # high|normal|low (SYNC-20)
  status             # SyncRunStatus
  range_start, range_end  nullable   # null for incremental
  plan               json nullable   # ImportPlan snapshot
  total_jobs         int default 0
  completed_jobs     int default 0
  records_created    bigint default 0
  records_updated    bigint default 0
  records_deleted    bigint default 0
  api_requests_used  int default 0
  error_message      text nullable
  correlation_id     string nullable
  started_at, completed_at, created_at, updated_at

clockify_sync_jobs
  id, sync_run_id, organization_id, workspace_id
  entity_type        # SyncEntityType
  phase              # reference|fact|derive
  range_start, range_end  nullable
  page               int default 0        # last completed page (resume point)
  page_size          int default 200
  records_processed  bigint default 0
  records_created    bigint default 0
  records_updated    bigint default 0
  records_deleted    bigint default 0
  status             # SyncJobStatus
  attempt            smallint default 0
  checkpoint         json nullable        # e.g. {last_changed_at, cursor}
  last_error         text nullable
  started_at, completed_at, heartbeat_at, next_retry_at
  index (sync_run_id, status), (sync_run_id, entity_type)

clockify_api_usage
  id, organization_id, connection_id, workspace_id
  window_type        # hour|second
  window_started_at, window_ends_at
  requests_used      int default 0
  requests_remaining int nullable
  limit_requests     int
  last_request_at, updated_at
  unique (connection_id, workspace_id, window_type, window_started_at)

clockify_entity_changes
  id, organization_id, workspace_id
  entity_type, clockify_id
  change_type        # EntityChangeType (CREATED|UPDATED|DELETED)
  source_at          timestamp          # Clockify change time
  detected_at        timestamp
  processed_at       timestamp nullable
  raw_data           json nullable
  index (organization_id, workspace_id, entity_type, processed_at)

clockify_deleted_entities
  id, organization_id, workspace_id
  entity_type, clockify_id
  deleted_at         timestamp
  document_code      string nullable
  applied_at         timestamp nullable
  raw_data           json nullable
  unique (organization_id, workspace_id, entity_type, clockify_id)

clockify_raw_records
  id, organization_id, workspace_id
  entity_type, clockify_id
  payload            json
  payload_hash       string
  source             string            # api|webhook|change_feed
  fetched_at         timestamp
  created_at, updated_at
  unique (organization_id, workspace_id, entity_type, clockify_id)
  index (entity_type, clockify_id)
```

Enums:
- `SyncTrigger`: `manual|scheduled|webhook|reconciliation|initial_import`.
- `SyncRunStatus`: `pending|running|paused|completed|failed|cancelled`.
- `SyncJobStatus`: `pending|running|completed|failed|cancelled|retry_scheduled`.
- `SyncEntityType`: mirrors Clockify types (`USER`, `PROJECTS`, `CLIENTS`,
  `TASKS`, `TAGS`, `TIME_ENTRY`, `TIME_ENTRY_RATE`,
  `TIME_ENTRY_CUSTOM_FIELD_VALUE`, `CUSTOM_FIELDS`, `USER_GROUPS`,
  `SCHEDULED_ASSIGNMENT`, `HOLIDAYS`, `PTO_POLICY`, `TIME_OFF_REQUEST`,
  `BALANCE`, `APPROVAL_REQUESTS`, `INVOICES`, `WORKSPACE`).
- `EntityChangeType`: `CREATED|UPDATED|DELETED`.

Linkage to the pipeline UI: a sync run is surfaced through `PIPE-01` events with
`run_type = SYNC` and `run_id = clockify_sync_runs.id`; `SYNC-14` emits them.

## 4. Backend
- **Models** `ClockifySyncRun`, `ClockifySyncJob`, `ClockifyApiUsage`,
  `ClockifyEntityChange`, `ClockifyDeletedEntity`, `ClockifyRawRecord` (org
  trait; casts for enums/dates/json).
- **Factories** for each (tests).
- **Repositories** one per table + contracts; bind in provider. Key methods:
  `SyncJobRepository::forRun`, `checkpoint($job)`, `advancePage($job, $page, $counts)`;
  `SyncRunRepository::countsRecalculate`.
- Migrations include all org/workspace/status indexes above.

## 5. Frontend / UI
- None. (Consumed by SYNC-14 + PIPE.)

### A11y & i18n
- Enum labels `__()`-wrapped for later rendering.

## 6. API / routes / props
- None directly.

## 7. Acceptance criteria
- [x] Migrating creates all six tables with the stated indexes and uniqueness.
- [x] Every table carries `organization_id`; models use the org scope.
- [x] A run can hold many jobs; a job tracks a resumable `page` checkpoint.
- [x] `api_usage` windows are unique per connection/workspace/type/start.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `SyncModelUnitTest`: casts, relations, status finality, enum labels.
- **Feature** `SyncSchemaTest`: tables exist, a run holds jobs with a resumable
  page, uniqueness on `api_usage` and `raw_records`, org scoping, and the
  `advancePage`/`countsRecalculate` repository helpers.

## 9. Notes & open questions
- `window_type` supports both Free (hour) and paid (second) accounting in one
  table (SYNC-02).
- Keep `payload` compressed? Defer; revisit with OPS-03 retention.
- **Implemented additions:** the inline fixed sets became enums (`SyncMode`,
  `SyncPriority`, `SyncPhase`, `ApiUsageWindowType`) per `AGENTS.md` §7.6.
  `ClockifyApiUsage` sets an explicit `$table = 'clockify_api_usage'` because the
  inflector would otherwise pluralize it to `clockify_api_usages`.
- The `workspace_id` columns are internal FKs to `clockify_workspaces` (not the
  Clockify id), matching the natural key `(organization_id, workspace_id,
  clockify_id)` used by the entity tables.
