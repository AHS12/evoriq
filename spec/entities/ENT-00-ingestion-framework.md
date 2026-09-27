# ENT-00 — Ingestion framework (per-entity contract)

- **Status:** Draft
- **Epic:** entities
- **Estimate:** L
- **Depends on:** ORG-01, SYNC-04, SYNC-05, SYNC-08, SYNC-07
- **Blocks:** ENT-01 … ENT-15
- **TDR:** §9, §24, §25, §26

## 1. Why

Every entity ingests identically: fetch → raw → map → idempotent upsert →
counters → deletion handling. Defining that once (this framework) makes the 15
entity specs formulaic and guarantees consistency, idempotency and resumability
across the whole dataset.

## 2. Scope

**In**
- The `SyncHandler` contract and its implementation pattern.
- Mapping conventions (Clockify payload → normalized attributes).
- Upsert contract binding (SYNC-08), org/workspace keying (ORG-01), raw store
  (SYNC-05), deletion policy (SYNC-07), counters.
- Pagination conventions per endpoint shape.
- The entity registry mapping `SyncEntityType` → handler.
- The shared test pattern.

**Out**
- Individual entity schemas/fields (ENT-01…ENT-15) and orchestration (SYNC-09).

## 3. Data model
- Every entity table: `id`, `organization_id`, `workspace_id`, `clockify_id`,
  timestamps, `synced_at`, `raw_data`, and `deleted_at` where the entity is a
  dimension or fact (per ENT spec).
- Natural key `(organization_id, workspace_id, clockify_id)` (SYNC-08).

## 4. Backend
- **Contract** `App\Services\Sync\Contracts\SyncHandler`:
  ```php
  interface SyncHandler {
      public function entityType(): SyncEntityType;
      public function phase(): SyncPhase;            // reference|fact
      public function fetchPage(SyncContext $ctx, int $page): iterable; // raw items
      public function map(array $raw, SyncContext $ctx): array;         // attributes
      public function repository(): SyncUpsertRepositoryInterface;
      public function delete(SyncContext $ctx, string $clockifyId): void;
  }
  ```
- **Context** `SyncContext`: `{ connection, workspace, organizationId, range,
  user (optional), pageSize }`.
- **Registry** `SyncHandlerRegistry` resolves `SyncEntityType` → handler; bound
  in a provider; new entity = one handler + one binding.
- **Base class** `AbstractSyncHandler` implementing fetch/upsert glue with
  `ClockifyClient` + `ApiUsageService` + `RawRecordStore` + repository so a
  handler only declares endpoint, mapping and deletion policy.
- **Pagination** (from `reference/clockify-api.md`):
  - base list endpoints → `page` + `page-size`, stop on `Last-Page: true`;
  - nested lists (tasks by project) → per-parent iteration;
  - time entries → **per user** (`ENT-07`);
  - entity changes → `page` + `limit` (SYNC-06).
- **Mapping rules:**
  - store the full payload in `raw_data`; map typed columns from documented
    fields;
  - parse booleans/enums/dates/decimals with casts;
  - resolve foreign keys internally (e.g. `clientId` → our `client_id` via
    `ENT-15`), tolerating missing parents (defer/flag);
  - never trust Clockify timestamps for ordering; use `synced_at` for freshness.
- **Deletion policy (SYNC-07):**
  - dimensions/facts → soft (`deleted_at`), keep rows for history;
  - join tables → delete join rows;
  - restore clears `deleted_at` when a later change references the id.
- **Counters:** every upsert returns `UpsertCounts` (created/updated/unchanged),
  accumulated by the runner (SYNC-04) onto job/run.
- **Errors:** a mapping failure for one row records a warning and continues
  (never fail the page for a single malformed row); a fetch failure follows
  SYNC-13.

### Entity → handler map (populated as specs land)

| Entity | Handler | Phase | Endpoint | Delete policy |
| --- | --- | --- | --- | --- |
| Workspace | `WorkspaceSyncHandler` | reference | `GET /workspaces/{id}` | n/a |
| Users | `UserSyncHandler` | reference | `GET /users` | soft |
| Memberships | `MembershipSyncHandler` | reference | user/profile + rates | replace |
| Clients | `ClientSyncHandler` | reference | `GET /clients` | soft |
| Projects | `ProjectSyncHandler` | reference | `GET /projects` | soft |
| Project members | `ProjectMemberSyncHandler` | reference | project memberships | replace |
| Tasks | `TaskSyncHandler` | reference | `GET /projects/{id}/tasks` | soft |
| Tags | `TagSyncHandler` | reference | `GET /tags` | soft |
| Time entries | `TimeEntrySyncHandler` | fact | `GET /user/{id}/time-entries` | soft |
| Time entry rates | `TimeEntryRateSyncHandler` | fact | entries + EPS | replace |
| Custom fields | `CustomFieldSyncHandler` | reference | `GET /custom-fields` | soft |
| TECF values | `TimeEntryCfValueSyncHandler` | fact | entries hydrated/changes | replace |
| User CF values | `UserCfValueSyncHandler` | reference | users/profile | replace |
| User groups | `UserGroupSyncHandler` | reference | `GET /user-groups` | replace |

## 5. Frontend / UI
- None. Entity labels/icons surface through the pipeline UI.

### A11y & i18n
- Entity names via `SyncEntityType::label()` (`__()`).

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [ ] New entities are added with a single handler + registry binding.
- [ ] All handlers share fetch/raw/upsert/counter behaviour.
- [ ] Mapping failures are per-row and non-fatal.
- [ ] Deletion/restore behaviour is uniform by class.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `SyncHandlerRegistryTest` + a `FakeSyncHandler` test for the
  base-class glue (fetch→raw→upsert, counters, per-row error tolerance).
- Each ENT spec adds its own handler/mapper test with `Http::fake()`.

## 9. Notes & open questions
- Foreign-key resolution may be temporarily unresolved on first import if
  parents arrive out of order; parents load in the reference phase first
  (SYNC-03) to minimize this.
