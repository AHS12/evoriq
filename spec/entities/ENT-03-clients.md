# ENT-03 — Clients

- **Status:** Draft
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
- [ ] All clients (incl. archived) sync with correct flags.
- [ ] Re-sync idempotent; archived→unarchived reflects.
- [ ] Soft delete handled (SYNC-07).
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ClientSyncHandlerTest` (`Http::fake()`): paging, archived flags,
  idempotency.

## 9. Notes & open questions
- Client currency may be null; fall back to workspace currency.
