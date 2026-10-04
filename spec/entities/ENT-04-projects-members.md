# ENT-04 — Projects & project members

- **Status:** Done
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
- [x] Projects map to the correct internal client; missing client handled.
- [x] Per-project member rates are stored and preserved.
- [x] Removing a project member reflects after re-sync; no duplicates.
- [x] Re-sync idempotent.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `ProjectSyncHandlerTest` + `ProjectMemberSyncHandlerTest`
  (`Http::fake()`): client resolution, rate mapping, membership replace.

## 9. Notes & open questions
- `UserIdWithRatesRequest`/membership schemas are not fully documented; map the
  documented fields and store raw for later enrichment.
- **Implemented notes:**
  - `clockify_projects` (soft-deletable) + `clockify_project_members` tables,
    `ClockifyProject`/`ClockifyProjectMember` models + factories and casts.
  - `ProjectSyncHandler` (reference phase) paginates
    `GET /workspaces/{ws}/projects?memberships=ALL&hydrated=true` (unknown params
    are ignored by Clockify). `hourlyRate`/`costRate` map to the billable/cost
    rate columns with the workspace currency as fallback; `estimate.estimate`
    (ISO-8601) is parsed to decimal `estimated_hours`.
  - `ProjectSyncRepository` keeps `clientId` as a reserved
    `client_clockify_id`, batch-resolves it to an internal `client_id` (missing
    clients → null), then upserts. This is a targeted slice of ENT-15.
  - Project members are derived from the same payload (there is no
    `PROJECT_MEMBER` entity type). The reserved `memberships` key is `null` when
    the payload omits the list (existing members left untouched) and an array
    otherwise; `ClockifyProjectMemberRepository::syncForProject` then deletes the
    project's rows and inserts the new set (replace), so removals reflect and
    re-runs never duplicate. Unresolved users are skipped.
  - `ClockifyProjectMemberRepositoryInterface` is bound in
    `RepositoryServiceProvider`; the handler is registered in
    `config/clockify.php`.
