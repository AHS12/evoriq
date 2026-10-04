# ENT-03 — Clients

- **Status:** Done
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00
- **Blocks:** ENT-04, ANA-* (client dimension)
- **TDR:** §25.5

## 1. Why

The client dimension drives "hours per client" and client trend reporting — a
core analytics goal.

## 2. Scope
**In:** `clockify_clients` from `GET /clients`.
**Out:** client CRUD.

## 3. Data model
```text
clockify_clients
  id, organization_id, workspace_id, clockify_id,
  name, email null, address null, note null, currency_code null,
  archived bool default false, archived_at null,
  created_at/updated_at/synced_at, raw_data, deleted_at
  unique (organization_id, workspace_id, clockify_id)
```

## 4. Backend
- `ClientSyncHandler`: paginate `GET /workspaces/{ws}/clients`
  (`page`,`page-size`, `archived` handling); map fields; upsert by natural key.
- Distinguish `archived` (Clockify) from `deleted_at` (SYNC-07).
- Deletion: soft.

## 5. Frontend / UI
- Client dimension powers client reports (REP-04) and dashboard distribution.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via report/dashboard resources.

## 7. Acceptance criteria
- [x] All clients (incl. archived) sync with correct flags.
- [x] Re-sync idempotent; archived→unarchived reflects.
- [x] Soft delete handled (SYNC-07).
- [x] `composer check` passes.

## 8. Tests
- **Unit** `ClientSyncHandlerTest` (`Http::fake()`): paging, archived flags,
  idempotency.

## 9. Notes & open questions
- Client currency may be null; fall back to workspace currency.
- **Implemented notes:**
  - `clockify_clients` (soft-deletable, org-owned) + `ClockifyClient` model,
    factory and casts. `archived`/`archived_at` are Clockify's own flag and are
    kept distinct from `deleted_at` (upstream deletion, SYNC-07).
  - `ClientSyncHandler` (reference phase) paginates
    `GET /workspaces/{ws}/clients` with `page`/`page-size`. It does **not** pass
    an `archived` filter, so the endpoint returns the full set (including
    archived) which is what the dimension needs.
  - `currency_code` falls back to the active workspace's currency when the
    client payload omits it; `archived` defaults to `false`.
  - Upsert is via the shared `AbstractSyncUpsertRepository` and the
    soft-delete-aware `UpsertsByClockifyId`, so a re-sync restores a deleted
    client instead of duplicating it.
