# ENT-01 — Workspace dimension

- **Status:** Draft
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
- [ ] Workspace fields (currency, time zone, week start, org id) are persisted.
- [ ] Missing optional fields do not break the sync.
- [ ] Re-sync updates without duplicating.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `WorkspaceSyncHandlerTest` with `Http::fake()` (full + partial payloads).

## 9. Notes & open questions
- `workspaceSettings`/`currencies` schemas are not expanded in the API reference;
  treat fields as optional and store the raw payload for later mapping.
