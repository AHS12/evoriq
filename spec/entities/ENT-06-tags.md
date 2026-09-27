# ENT-06 — Tags & time-entry tags

- **Status:** Draft
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00, ENT-07
- **Blocks:** ANA-* (tag dimension)
- **TDR:** §25.9

## 1. Why

Tags enable flexible categorization. Storing them **relationally** (not as a JSON
array on the time entry) keeps hours-by-tag, tag trends and billable-by-tag
queryable — an explicit TDR requirement.

## 2. Scope
**In:** `clockify_tags` from `GET /tags`; `clockify_time_entry_tags` join rows
derived from ingested time entries (`tagIds[]`).
**Out:** tag CRUD.

## 3. Data model
```text
clockify_tags
  id, organization_id, workspace_id, clockify_id, name,
  archived bool default false, archived_at null,
  created_at/updated_at/synced_at, raw_data, deleted_at
  unique (organization_id, workspace_id, clockify_id)

clockify_time_entry_tags
  time_entry_id, tag_id
  primary (time_entry_id, tag_id)
```

## 4. Backend
- `TagSyncHandler`: paginate tags; upsert; soft delete.
- Join rows: written by `TimeEntrySyncHandler` (ENT-07) from `tagIds[]` —
  delete–insert per entry within the page transaction so tag changes reflect.
- Resolution: `tagIds[]` → internal tag ids via ENT-15; unknown tag ids are
  flagged (a tag may not have synced yet — reference phase avoids this).

## 5. Frontend / UI
- Tag dimension in reports (REP-05) and tag trends.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via report resources.

## 7. Acceptance criteria
- [ ] Tags sync idempotently; archived flags correct.
- [ ] Time-entry tag joins reflect adds/removals after re-sync.
- [ ] Unknown tag ids are handled without failing the entry.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `TagSyncHandlerTest` (`Http::fake()`) + join-write test via
  `TimeEntrySyncHandler`.

## 9. Notes & open questions
- Join rows are owned jointly by ENT-06 and ENT-07; document the ownership to
  avoid double-writing.
