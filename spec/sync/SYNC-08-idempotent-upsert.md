# SYNC-08 — Idempotent upsert conventions

- **Status:** Done
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-01
- **Blocks:** ENT-*, SYNC-04
- **TDR:** §24, §41

## 1. Why

Repeated imports must never create duplicates. A single, enforced conformance
across every entity repository is what makes resume/retry/re-import safe. This
spec pins the natural key, the upsert contract, and how we count
creates-vs-updates.

## 2. Scope

**In**
- The natural key and upsert contract for every synced entity.
- A shared trait/contract for repositories.
- Create-vs-update detection for accurate counters.
- Idempotency guarantees and tests.

**Out**
- The specific entity schemas (ENT-*); the job engine (SYNC-04).

## 3. Data model
- The natural key: **`(organization_id, workspace_id, clockify_id)`** on every
  normalized entity (ORG-01 + entity specs).
- Fact tables whose Clockify id is not the row identity (join rows) use the
  composite of their parents plus the Clockify id where present.

## 4. Backend
- **Contract** `App\Repositories\Contracts\SyncUpsertRepositoryInterface`:
  - `upsertByClockifyId(workspace, array $attributes): Model`
  - `upsertMany(workspace, iterable $rows): UpsertCounts`
    `UpsertCounts { created, updated, unchanged }`.
- **Trait** `App\Repositories\Concerns\UpsertsByClockifyId` implementing
  `upsertMany` with `updateOrCreate(['organization_id','workspace_id',
  'clockify_id'], $attributes)` and create-detection via `wasRecentlyCreated`.
- **Rules:**
  - `clockify_id` is the only external join key; internal ids never leave.
  - Absence from a page is **not** a deletion (deletions only via SYNC-07).
  - Writes are chunked and run inside the page transaction (SYNC-04).
  - `synced_at` updated on every write; `raw_data` updated with the payload.
- **Counters:** each handler returns `UpsertCounts`; the runner accumulates onto
  the job/run (`records_created/updated`).
- **Conflict handling:** if Clockify ids collide across workspaces (should not),
  the composite key prevents cross-workspace overwrite.

## 5. Frontend / UI
- None; counters surface through the pipeline UI.

### A11y & i18n
- None.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [x] Running the same page twice creates zero duplicates and reports 0 created.
- [x] `upsertMany` returns accurate created/updated/unchanged counts.
- [x] Natural key is `(organization_id, workspace_id, clockify_id)` on every
      entity.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `UpsertConventionTest`: double-apply idempotency, count accuracy,
  composite-key safety across workspaces.
- Applied per entity in ENT specs.

## 9. Notes & open questions
- Bulk upsert performance: `updateOrCreate` per row is fine at our volumes; use
  `upsert()` for very large fact batches if needed, accepting the create/update
  count caveat.
- **Implemented:** `UpsertsByClockifyId` holds the behaviour; entity repositories
  extend `AbstractSyncUpsertRepository` and declare `syncModel()`. Created rows
  are detected via `wasRecentlyCreated`; updated-vs-unchanged is decided by
  comparing the incoming row against the pre-update snapshot, ignoring the key
  columns and the always-refreshed `synced_at` (otherwise every re-run would look
  "updated"). The base class also gives PHPStan an analysed usage of the trait
  before the first entity lands.
