# ENT-01 — Workspace dimension

- **Status:** Done
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00, CONN-03
- **Blocks:** SYNC-03, ANA-07
- **TDR:** §25.2

## 1. Why

The workspace is Clockify's root boundary and the source of currency, time zone
and default rates. Every fact is scoped to it, and analytics needs its time zone
to bucket days correctly.

## 2. Scope
**In:** full `clockify_workspaces` dimension sync from `GET /workspaces/{id}` +
`GET /workspaces` (already discovered in CONN-03; ENT-01 enriches).
**Out:** workspace CRUD, plan detection (CONN-02).

## 3. Data model
Extends `clockify_workspaces` (CONN-03) with: `default_billable`,
`default_hourly_rate`, `default_cost_rate`, `workspace_settings` json,
`cake_organization_id`, `feature_subscription_type`. `raw_data` retained.

## 4. Backend
- `WorkspaceSyncHandler` (reference phase): fetch workspace object, map, upsert
  by `(org, clockify_id)`.
- Map `time_zone`/`week_start`/`currency` from `workspaceSettings`/`currencies`
  (shapes partially documented — tolerate nulls).
- Store `cake_organization_id` to keep org mapping authoritative (ORG-01).
- Deletion policy: n/a (workspaces retire, not delete).

## 5. Frontend / UI
- Used by FND-03/SYNC-18 for time zone/currency display. No dedicated page.

### A11y & i18n
- Currency/time zone rendered via `Intl` (FND-02).

## 6. API / routes / props
- Workspace summary in `connectionStatus` (CONN-08).

## 7. Acceptance criteria
- [x] Workspace fields (currency, time zone, week start, org id) are persisted.
- [x] Missing optional fields do not break the sync.
- [x] Re-sync updates without duplicating.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `WorkspaceSyncHandlerTest` with `Http::fake()` (full + partial payloads).

## 9. Notes & open questions
- `workspaceSettings`/`currencies` schemas are not expanded in the API reference;
  treat fields as optional and store the raw payload for later mapping.
- **Implemented notes:**
  - `clockify_workspaces` gained `default_billable`, `default_hourly_rate`
    (`decimal:2`), `default_cost_rate` (`decimal:2`), `workspace_settings` (json)
    and an indexed `cake_organization_id`; the model/factory/casts were updated.
  - Shared `App\Support\Clockify\WorkspaceMapper` normalizes the payload for
    both connect-time discovery (`WorkspaceService`, CONN-03) and the sync
    handler, so there is a single mapping source.
  - `WorkspaceSyncHandler` (`reference` phase) fetches `GET /workspaces/{id}`,
    maps, and upserts via `WorkspaceSyncRepository`. The handler drops absent
    (null) fields so a partial detail response never erases stored values; the
    deletion policy is `NONE`.
  - The workspace dimension is its own parent, so `WorkspaceSyncRepository`
    overrides `UpsertsByClockifyId::syncUsesWorkspace()` to key by
    `(organization_id, clockify_id)` instead of the generic three-column key
    (the trait gained that hook for exactly this case).
