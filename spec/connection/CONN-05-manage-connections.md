# CONN-05 — Manage connections

- **Status:** Draft
- **Epic:** connection
- **Estimate:** M
- **Depends on:** CONN-02
- **Blocks:** CONN-09, SYNC-19
- **TDR:** §39

## 1. Why

Keys rotate, workspaces change, and users need to disconnect — sometimes keeping
imported data, sometimes purging it. Connection lifecycle must be explicit,
audited and safe.

## 2. Scope

**In**
- List connections with status, workspace, plan, last verified.
- Rename, re-verify, rotate API key, disable/enable, disconnect.
- Disconnect with a data-retention choice ("keep imported data" vs "delete
  synced data").
- Audit every lifecycle action.

**Out**
- Credential storage (CONN-01), webhook lifecycle (SYNC-19), settings (CONN-09).

## 3. Data model
- Uses `clockify_connections` (+ `status`). Disconnect-with-purge schedules a
  data deletion job (OPS-05) rather than deleting inline.

## 4. Backend
- **Routes** (`auth`, `verified`, `can:connection.manage`):
  - `PUT /connections/{connection}` (`connections.update`) — name/region.
  - `POST /connections/{connection}/verify` (`connections.reverify`).
  - `POST /connections/{connection}/key` (`connections.rotate-key`).
  - `POST /connections/{connection}/disable` / `/enable`.
  - `DELETE /connections/{connection}` (`connections.destroy`) with
    `?purge=1|0`.
- **Service** `ClockifyConnectionService`: `rename`, `reverify`, `rotateKey`,
  `disable`, `enable`, `disconnect($purge)`. All transactional; audit events
  (`connection.updated|reverified|key_rotated|disabled|enabled|disconnected`).
- **Purge:** dispatch a queued cleanup (Data Processing Center / OPS-05) scoped
  to the connection's workspace + org; never synchronous.
- **Safety:** disabling stops all syncs for the connection; the scheduler skips
  disabled connections.

## 5. Frontend / UI
- Connections list page (extends `connections/index.tsx`): status badge, plan,
  workspace, last verified, actions menu.
- **Disconnect dialog** (`ConfirmDialog`): explains consequences and offers a
  switch "Delete imported Clockify data" (default off).
- Key rotation dialog: paste new key, verify, then replace.

### A11y & i18n
- Destructive dialog is explicit; consequences translated.

## 6. API / routes / props
- `connections.index` props add `{ status, plan, workspace, last_verified_at }`.

## 7. Acceptance criteria
- [ ] Rotating a key re-verifies before replacing; failure keeps the old key.
- [ ] Disabling stops scheduled and manual syncs.
- [ ] Disconnect with purge queues deletion and reports it as a tracked job.
- [ ] Every action is audited with actor attribution.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ConnectionLifecycleUnitTest`: rotate success/failure, disable skips
  schedule, purge dispatch.
- **Feature** `ConnectionManageFeatureTest`: endpoints + authorization + audit
  rows.

## 9. Notes & open questions
- Purging synced data is destructive and cross-module; coordinate with OPS-05 and
  make it explicit which tables are affected.
