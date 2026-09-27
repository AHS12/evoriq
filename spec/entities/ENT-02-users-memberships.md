# ENT-02 — Users & workspace memberships

- **Status:** Draft
- **Epic:** entities
- **Estimate:** L
- **Depends on:** ORG-01, ENT-00
- **Blocks:** ENT-04, ENT-07, ENT-12, ANA-* (user dimension)
- **TDR:** §25.3, §25.4

## 1. Why

Users are the people dimension and the key fan-out for time-entry ingestion
(per-user fetch). Memberships carry rates that change over time and must be
preserved — not overwritten blindly.

## 2. Scope
**In:** `clockify_users` identity + `clockify_memberships` (workspace/project/
user-group relationship + rates), from `GET /users` (+ profiles for capacity/
working days), and the workspace-rate endpoints.
**Out:** project-level rate detail owned by ENT-04.

## 3. Data model
```text
clockify_users
  id, organization_id, workspace_id, clockify_id,
  name, email, status, profile_picture_url,
  timezone null, week_start null, working_days json null, work_capacity null,
  created_at/updated_at/synced_at, raw_data, deleted_at

clockify_memberships
  id, organization_id, workspace_id, user_id,
  membership_type (workspace|project|usergroup), target_type, target_id,
  hourly_rate_amount null, hourly_rate_currency null,
  cost_rate_amount null, cost_rate_currency null,
  effective_from null, created_at/updated_at, raw_data
  index (organization_id, workspace_id, user_id)
```

## 4. Backend
- `UserSyncHandler`: `GET /workspaces/{ws}/users?page&page-size` with
  `memberships=ALL`; map identity (status enum PENDING/ACTIVE/DECLINED/INACTIVE).
- `MembershipSyncHandler`: derive workspace membership + rates; project
  memberships are owned by ENT-04; user-group membership by ENT-12.
- Capacity: profile endpoint gives `weekStart`, `workCapacity` (ISO-8601, e.g.
  `PT7H`), `workingDays` (JSON string) — parse and store.
- Rates: from workspace rate fields/profiles; represent
  `{amount, currency, effective_from}`; **append/history** rather than overwrite
  when `since` changes.
- Deletion: soft (`deleted_at`) on users; membership rows for a removed
  user/workspace relationship are ended (soft or removed).

## 5. Frontend / UI
- User dimension powers reports/dashboards; no dedicated CRUD page in MVP.

### A11y & i18n
- Status labels translated.

## 6. API / routes / props
- User dimension exposed via report/dashboard resources (REP/DASH).

## 7. Acceptance criteria
- [ ] All workspace users are synced with status, timezone, capacity, working
      days.
- [ ] Workspace rates are preserved historically (not overwritten blindly).
- [ ] Re-sync is idempotent.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `UserSyncHandlerTest` + `MembershipSyncHandlerTest` (`Http::fake()`);
  ISO-duration parsing; working-days parsing; rate history append.

## 9. Notes & open questions
- `memberships` object schema isn't fully documented; derive what we can and
  store raw. Confirm rate-history requirement with product.
