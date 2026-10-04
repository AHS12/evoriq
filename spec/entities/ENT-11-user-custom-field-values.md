# ENT-11 — User custom field values

- **Status:** Done
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
- [x] User custom field values sync with correct JSON values.
- [x] Re-sync idempotent; removals reflected.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `UserCfValueSyncHandlerTest` (`Http::fake()`).

## 9. Notes & open questions
- Values may arrive on the users list, the profile endpoint, or via user CF
  updates; pick the cheapest source and document it.
- **Implemented notes:**
  - **Cheapest source:** the values are derived during the **USER sync (ENT-02)**
    from the already-fetched `GET /users?memberships=ALL` payload — there is no
    `USER_CUSTOM_FIELD_VALUE` entity type, and a separate handler would re-fetch
    the users list for free otherwise. The handler maps a reserved
    `custom_field_values` key (from `customFieldValues`/`userCustomFieldValues`,
    absent → null = leave untouched).
  - `clockify_user_custom_field_values` (FKs to users + custom fields, cascade) +
    `ClockifyUserCustomFieldValue` model/factory; unique
    `(organization_id, workspace_id, user_id, custom_field_id)`; JSON `value`.
  - `UserCfValueRepository` (bound as `UserCfValueRepositoryInterface`) replaces
    a user's rows (delete–insert); unknown custom fields are skipped.
    `UserSyncRepository` writes users, memberships and custom field values in one
    pass.
  - The spec's nominal `UserCfValueSyncHandler` is intentionally folded into
    `UserSyncHandler` (same reasoning as ENT-02's memberships); the dedicated
    test lives in `UserCustomFieldValueTest`.
