# ENT-11 — User custom field values

- **Status:** Draft
- **Epic:** entities
- **Estimate:** S
- **Depends on:** ORG-01, ENT-00, ENT-02, ENT-09
- **Blocks:** ANA-* (user/team metadata)
- **TDR:** §25.14

## 1. Why

User custom fields carry department, location, employee type, team and cost
center — dimensions that make user/team analytics far more useful.

## 2. Scope
**In:** `clockify_user_custom_field_values` from user profiles / users list.
**Out:** entry values (ENT-10), definitions (ENT-09).

## 3. Data model
```text
clockify_user_custom_field_values
  id, organization_id, workspace_id, user_id, custom_field_id, value json,
  created_at/updated_at, raw_data
  unique (organization_id, workspace_id, user_id, custom_field_id)
```

## 4. Backend
- `UserCfValueSyncHandler` (reference phase): read `userCustomFieldValues` from
  user/profile payloads; upsert by `(user, field)`; resolve `customFieldId`.
- Delete–insert per user for removals.

## 5. Frontend / UI
- None; enables user/team metadata dimensions in reports.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via analytics.

## 7. Acceptance criteria
- [ ] User custom field values sync with correct JSON values.
- [ ] Re-sync idempotent; removals reflected.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `UserCfValueSyncHandlerTest` (`Http::fake()`).

## 9. Notes & open questions
- Values may arrive on the users list, the profile endpoint, or via user CF
  updates; pick the cheapest source and document it.
