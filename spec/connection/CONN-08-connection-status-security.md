# CONN-08 — Connection status & security verification

- **Status:** Done
- **Epic:** connection
- **Estimate:** S
- **Depends on:** CONN-01, CONN-02
- **Blocks:** SYNC-18, DASH-05
- **TDR:** §13, §14, §35, §39

## 1. Why

Users need at-a-glance confidence that the connection is healthy and safe:
which workspace/plan is connected, when it was last verified, and that the key
is stored encrypted and never exposed. This is the credential-side counterpart
to the pipeline status.

## 2. Scope

**In**
- A connection status summary exposed as a typed resource/prop: workspace, plan,
  budget, status, last verified, last sync (via SYNC-18).
- Surface on the dashboard and connections page.
- Encryption/redaction verification (tests) proving secrets never leave the
  server.

**Out**
- The live API-budget popover (SYNC-17).
- Sync freshness numbers (SYNC-18 provides them).

## 3. Data model
- None.

## 4. Backend
- **Resource** `ConnectionStatusResource`: `{ id, name, status, workspace, plan,
  budget: { requests_per_hour? , requests_per_second? }, webhook_limit,
  last_verified_at }`.
- **Service** `ConnectionStatusService::forOrganization()` for dashboard use.
- **Security tests:** assert `api_key`/`addon_token` hidden; assert audit and
  logs contain no secret; assert `encrypted` cast is applied.

## 5. Frontend / UI
- Dashboard/settings card: status dot + plan badge + "verified :time ago" +
  workspace name + a link to manage (CONN-05).
- Rendered from `ConnectionStatusResource`; never shows key material.

### A11y & i18n
- Status conveyed by text + icon, not colour alone; strings translated.

## 6. API / routes / props
- `connections.index` and the dashboard include `connectionStatus`.

## 7. Acceptance criteria
- [x] Status card reflects workspace, plan and last-verified accurately.
- [x] A test proves no credential appears in any resource/audit/log.
- [x] Card links to connection management.
- [x] `composer check` passes.

## 8. Tests
- **Feature** `ConnectionStatusFeatureTest`: prop shape; secrets absent;
  dashboard renders the card.
- **Unit** `ConnectionStatusServiceTest`: plan/budget projection.

## 9. Notes & open questions
- Last-synced/freshness comes from SYNC-18; this spec only owns the credential/
  plan portion.

### Implemented notes

- `ConnectionStatusResource` exposes
  `{ id, name, status, workspace, plan, budget, webhook_limit, last_verified_at }`
  and never serializes `api_key`/`addon_token`.
- `ConnectionStatusService::forOrganization()` resolves the active connection;
  both `connections.index` and the dashboard receive `connectionStatus`
  (nullable).
- Frontend `ConnectionStatusCard` renders a status dot, plan badge, workspace
  and last-verified time, linking to `connections.index`; shown on the
  connections page and the dashboard.
