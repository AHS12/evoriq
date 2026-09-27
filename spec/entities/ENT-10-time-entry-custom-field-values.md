# ENT-10 — Time entry custom field values

- **Status:** Draft
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00, ENT-07, ENT-09
- **Blocks:** ANA-* (custom dimensions)
- **TDR:** §25.13

## 1. Why

Custom metadata on entries is itself a historical fact (the Entity Changes API
treats `TIME_ENTRY_CUSTOM_FIELD_VALUE` as its own entity). Keeping it in its own
table — not JSON on the entry — preserves that and keeps it queryable.

## 2. Scope
**In:** `clockify_time_entry_custom_field_values` from hydrated entries
(`customFieldValues`) and `TIME_ENTRY_CUSTOM_FIELD_VALUE` changes.
**Out:** user values (ENT-11), definitions (ENT-09).

## 3. Data model
```text
clockify_time_entry_custom_field_values
  id, organization_id, workspace_id,
  time_entry_id, custom_field_id, value json,
  created_at/updated_at, raw_data
  unique (organization_id, workspace_id, time_entry_id, custom_field_id)
```

## 4. Backend
- `TimeEntryCfValueSyncHandler` (fact phase): from `customFieldValues[]` on
  hydrated entries (ENT-07) and from entity changes; upsert by
  `(entry, field)`. Delete–insert per entry so removed values disappear.
- Resolve `customFieldId` → internal id (ENT-15); unknown field → skip + warn.
- Value is JSON (text/number/bool/array depending on field type).

## 5. Frontend / UI
- None; enables metadata grouping in reports later.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via analytics.

## 7. Acceptance criteria
- [ ] Values sync per entry/field, including arrays for multi-select.
- [ ] Removed values disappear after re-sync; no duplicates.
- [ ] Unknown custom fields are skipped without failing the entry.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `TimeEntryCfValueSyncHandlerTest` (`Http::fake()`): text/number/
  multi-select values, removal, idempotency.

## 9. Notes & open questions
- `customFieldValues` schema on hydrated entries isn't fully expanded; validate
  the actual shape during implementation.
