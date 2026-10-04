# SYNC-07 — Deleted entities application

- **Status:** Done
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-06
- **Blocks:** ENT-13, SYNC-10
- **TDR:** §20.2, §24, §41

## 1. Why

A record's absence from a page is **not** a deletion; deletions arrive only via
the deleted-entities feed. If we ignore them, a deleted Clockify project keeps
existing in our DB and silently corrupts historical reports. Applying deletions
correctly is core sync correctness.

## 2. Scope

**In**
- Persist deleted entities (`clockify_deleted_entities`) and **apply** them to
  the normalized model.
- Deletion semantics per entity: soft-delete/hide dimensions vs remove
  join rows vs mark facts; never destroy historical facts needed for reports.
- Handling restores (an entity deleted then recreated/updated).
- Idempotent application.

**Out**
- Fetching the feed (SYNC-06); entity upserts (ENT-13).

## 3. Data model
- `clockify_deleted_entities` (SYNC-01) with `applied_at`.
- Normalized entities carry `deleted_at`/`archived` markers as defined per ENT
  spec (or a shared `sync_deleted_at`).

## 4. Backend
- **Service** `Services\Sync\DeletionApplier`:
  - `ingest(workspace, deletedItems[])` → upsert `clockify_deleted_entities`.
  - `applyPending(workspace, limit)` → for each unapplied deletion, dispatch to
    the entity handler's `delete(clockifyId)`.
- **Contract** extends `SyncHandler` with `delete(workspace, clockifyId): void`.
- **Per-entity policy** (documented per ENT spec):
  - Dimensions (project, task, tag, client, user): mark `deleted_at` (keep rows
    so historical entries keep resolving names) — **do not** hard-delete.
  - Join tables (time-entry tags, project members): remove the join rows.
  - Facts (time entries, rates, custom-field values): mark `deleted_at`; keep
    the row so aggregates can exclude it deliberately.
- **Restore:** if a later `created/updated` change references a soft-deleted id,
  clear `deleted_at` and re-apply the payload.
- **Order safety:** apply deletions before/independent of updates so a
  delete+recreate cycle converges.
- **Audit/metrics:** count applied deletions per run.

## 5. Frontend / UI
- Deleted counts surface in `PipelineRunResource.counts.deleted` (PIPE-02) and
  run reports (PIPE-06).

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [x] Deleted entities from the feed are recorded and applied idempotently.
- [x] Historical reports remain consistent (names still resolve via soft-deleted
      dimensions; facts excluded from aggregates when marked deleted).
- [x] A delete→recreate converges to the live state.
- [x] Re-applying the same deletion is a no-op.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `DeletionApplierTest`: ingest idempotency, handler dispatch + applied
  marking, unregistered-handler skip.
- **Feature** `DeletedEntityFeatureTest`: soft-delete then restore via re-upsert;
  re-applying is a no-op.

## 9. Notes & open questions
- Decide whether "archived" (a Clockify concept) and "deleted" share a column or
  stay separate; keep them distinct to avoid conflating.
- **Implemented notes:**
  - The per-entity policy lives in each `SyncHandler::delete()` (dimensions/facts
    soft-delete, join tables remove rows); `DeletionApplier` only dispatches and
    marks `applied_at`. Unregistered entity types are left unapplied rather than
    crashing the run.
  - Restore is generic: `UpsertsByClockifyId::syncValues()` clears `deleted_at`
    on re-upsert when the target table has that column, so a delete→recreate
    converges without per-entity code.
  - `ingest` accepts the feed's `EntityChangeDTO[]`, keeps only `DELETED`, and
    resets `applied_at` on (re-)ingest so a delete→restore→delete cycle is
    re-applied. `unapplied()` is now workspace-scoped.
