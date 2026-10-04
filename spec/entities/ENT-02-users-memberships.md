# ENT-02 — Users & workspace memberships

- **Status:** Done
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
- [x] All workspace users are synced with status, timezone, capacity, working
      days.
- [x] Workspace rates are preserved historically (not overwritten blindly).
- [x] Re-sync is idempotent.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `UserSyncHandlerTest` + `MembershipSyncHandlerTest` (`Http::fake()`);
  ISO-duration parsing; working-days parsing; rate history append.

## 9. Notes & open questions
- `memberships` object schema isn't fully documented; derive what we can and
  store raw. Confirm rate-history requirement with product.
- **Implemented notes:**
  - `clockify_users` (soft-deleted, org-owned) + `clockify_memberships` tables,
    models, factories and casts. `ClockifyUserStatus` and `ClockifyMembershipType`
    are enums.
  - Memberships are **derived in the `USER` handler** from
    `GET /workspaces/{ws}/users?memberships=ALL`: there is no `MEMBERSHIP`
    entity type, so re-fetching users for a separate job would be wasteful.
    `UserSyncRepository` writes the user, then hands the reserved `memberships`
    list to `ClockifyMembershipRepository`. ENT-02 owns workspace memberships
    only; project/user-group rows land in ENT-04/ENT-12.
  - `work_capacity` stores **parsed seconds** (`Iso8601Duration`, e.g. `PT7H30M`
    → `27000`); `working_days` is parsed from the JSON string; status/capacity
    are optional and tolerant.
  - Rate history: membership rows are keyed by target + `effective_from`, so a
    rate that starts at a new `since` appends a row; a null `effective_from`
    updates the single current row.
  - `UpsertsByClockifyId` now queries without the `SoftDeletingScope`, so a
    re-sync finds a soft-deleted row and restores it instead of colliding on the
    natural key. (Users need `deleted_at` fillable for the restore fill to
    apply.)
  - Deletion of relationships remains out of scope here (full-snapshot sync
    treats absence as "not a deletion", SYNC-08); explicit deletes arrive with
    SYNC-07/ENT-13.
