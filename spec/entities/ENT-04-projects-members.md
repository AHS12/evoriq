# ENT-04 — Projects & project members

- **Status:** Draft
- **Epic:** entities
- **Estimate:** L
- **Depends on:** ORG-01, ENT-00, ENT-02, ENT-03
- **Blocks:** ENT-05, ENT-07, ANA-* (project dimension)
- **TDR:** §25.6, §25.7

## 1. Why

Projects are the **primary reporting dimension**. Project memberships carry
per-user rates that differ from workspace rates and must be preserved for
historical cost/billable accuracy.

## 2. Scope
**In:** `clockify_projects` + `clockify_project_members` from `GET /projects`
(`memberships=ALL`, `hydrated=true`) and project membership/rate endpoints.
**Out:** project CRUD.

## 3. Data model
```text
clockify_projects
  id, organization_id, workspace_id, clockify_id, client_id null,
  name, color null, note null, status null,
  archived bool default false, archived_at null,
  billable bool default false, public bool default true,
  billable_rate_amount null, billable_rate_currency null,
  cost_rate_amount null, cost_rate_currency null,
  estimated_hours null, estimated_cost null,
  created_at/updated_at/synced_at, raw_data, deleted_at

clockify_project_members
  id, organization_id, workspace_id, project_id, user_id,
  membership_type, membership_status null,
  hourly_rate_amount null, hourly_rate_currency null,
  cost_rate_amount null, cost_rate_currency null,
  created_at/updated_at, raw_data
  unique (organization_id, workspace_id, project_id, user_id)
```

## 4. Backend
- `ProjectSyncHandler`: paginate projects; map `clientId` → internal `client_id`
  (ENT-15); rates as `{amount, since}`.
- `ProjectMemberSyncHandler`: from `memberships`/`UserIdWithRatesRequest`; replace
  the member set per project (delete–insert within the page transaction) so
  removals are reflected.
- Deletion: projects soft; membership rows replace.
- Parent ordering: clients + users sync first (SYNC-03 reference phase).

## 5. Frontend / UI
- Project dimension powers project reports (REP-03), dashboard distribution,
  growth/trend.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Exposed via report/dashboard resources.

## 7. Acceptance criteria
- [ ] Projects map to the correct internal client; missing client handled.
- [ ] Per-project member rates are stored and preserved.
- [ ] Removing a project member reflects after re-sync; no duplicates.
- [ ] Re-sync idempotent.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ProjectSyncHandlerTest` + `ProjectMemberSyncHandlerTest`
  (`Http::fake()`): client resolution, rate mapping, membership replace.

## 9. Notes & open questions
- `UserIdWithRatesRequest`/membership schemas are not fully documented; map the
  documented fields and store raw for later enrichment.
