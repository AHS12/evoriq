# SYNC-05 — Raw record store

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-01
- **Blocks:** OPS-02, SYNC-06
- **TDR:** §25.26, §27

## 1. Why

If Clockify changes a response shape, or a needed field is discovered later, the
normalized entity can be rebuilt only if the raw upstream payload was stored.
This is our recovery layer and a prerequisite for safe, re-runnable imports.

## 2. Scope

**In**
- `clockify_raw_records` persistence keyed by `(org, workspace, entity_type,
  clockify_id)` with content hash and source.
- A small service used by sync jobs, webhooks and change-feed processing.
- Retention policy hook (deferred execution to OPS-03).

**Out**
- Rebuild tooling (OPS-02); entity mapping (ENT-*).

## 3. Data model
- `clockify_raw_records` (SYNC-01). `payload_hash` = `sha256(json_encode(payload))`
  to detect unchanged payloads.

## 4. Backend
- **Service** `Services\Sync\RawRecordStore`:
  - `put(org, workspace, entityType, clockifyId, payload, source)` — upsert;
    skip the write when the payload hash is unchanged (cheap dedupe, big saving
    on re-runs).
  - `get(...)`, `deleteFor(...)`.
  - `putMany(...)` for page batches (single upsert call).
- **Repository** `ClockifyRawRecordRepository` (`upsertMany`, `find`, `prune`).
- **Wiring:** `SyncJobRunner` calls `putMany` for each fetched page before
  normalization.
- **Retention:** `source` distinguishes `api|webhook|change_feed`; retention
  config provided here, executed by OPS-03.

## 5. Frontend / UI
- None.

### A11y & i18n
- None.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [ ] Every fetched page's payloads are persisted with a stable hash.
- [ ] Unchanged payloads do not create new writes (hash dedupe).
- [ ] Records are keyed and unique by org/workspace/entity/clockify id.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `RawRecordStoreTest`: put/dedupe/hash, putMany, delete-for.
- **Feature** `RawRecordPersistenceTest`: after a sync run, raw rows exist and
  match the faked payloads.

## 9. Notes & open questions
- Storage growth: raw records are the largest table; confirm compression/
  pruning defaults in OPS-03 before production.
