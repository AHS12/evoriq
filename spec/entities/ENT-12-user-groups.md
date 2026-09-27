# ENT-12 — User groups & members

- **Status:** Draft
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00, ENT-02
- **Blocks:** ANA-* (team dimension)
- **TDR:** §25.15

## 1. Why

Groups unlock hours by team, utilization by team and billable percentage by team
— valuable team-level analytics and a natural dimension for reports.

## 2. Scope
**In:** `clockify_user_groups` + `clockify_user_group_members` from
`GET /user-groups`.
**Out:** group CRUD.

## 3. Data model
```text
clockify_user_groups
  id, organization_id, workspace_id, clockify_id, name, status null,
  created_at/updated_at/synced_at, raw_data, deleted_at
  unique (organization_id, workspace_id, clockify_id)

clockify_user_group_members
  user_group_id, user_id
  primary (user_group_id, user_id)
```

## 4. Backend
- `UserGroupSyncHandler` (reference phase, MVP/Phase 2 boundary): paginate
  `GET /user-groups`; store `teamManagers[]` refs and `userIds[]`.
- Members: delete–insert per group within the page transaction so membership
  changes reflect; resolve `userIds` → internal user ids (ENT-15).
- Deletion: group soft; members replace.

## 5. Frontend / UI
- Team dimension in reports/dashboards (later).

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via analytics.

## 7. Acceptance criteria
- [ ] Groups and memberships sync idempotently.
- [ ] Membership removals reflected after re-sync.
- [ ] Unknown user ids handled without failing.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `UserGroupSyncHandlerTest` (`Http::fake()`): groups + member replace.

## 9. Notes & open questions
- Classified `MVP / Phase 2` in TDR §9.3; implement in the reference phase but it
  may ship after the core MVP entities.
