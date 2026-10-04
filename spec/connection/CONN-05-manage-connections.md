# CONN-05 — Manage connections

- **Status:** Done
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
- **Disconnect / purge — deferred to OPS-05.** Rename, re-verify, rotate key and
  disable/enable ship now; the destructive disconnect + data-retention choice
  lands with the retention/deletion tooling in OPS-05.

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
- [x] Rotating a key re-verifies before replacing; failure keeps the old key.
- [x] Disabling stops scheduled and manual syncs (disabled connections are
      excluded by the active-connection lookup the scheduler uses).
- [ ] Disconnect with purge queues deletion and reports it as a tracked job.
      **(deferred to OPS-05)**
- [x] Every action is audited with actor attribution.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `ConnectionLifecycleUnitTest`: rename, reverify/rotate delegation,
  disable/enable audit, disabled connections skipped by the active lookup, and
  rotate success/failure (old key preserved).
- **Feature** `ConnectionManageFeatureTest`: endpoints + authorization + audit
  rows + secret redaction.

## 9. Notes & open questions
- Purging synced data is destructive and cross-module; coordinate with OPS-05 and
  make it explicit which tables are affected. Deferred with the disconnect flow.

### Implemented notes

- Rename, re-verify, rotate-key, disable/enable ship with routes, service
  methods, audit events and the connections-list action menu
  (`connection-card-actions`, `rename-connection-dialog`,
  `rotate-key-dialog`).
- The destructive disconnect + purge choice remains deferred to OPS-05 (the
  only unchecked acceptance item); everything else in this spec is done.
- **MVP single-connection limit:** because the import wizard is built around one
  active connection (no picker), the app now supports **at most one** connection:
  `connections.index` hides the Add action (`canCreate = create && ! hasAny()`)
  and `ConnectionController@store` rejects a second one with a toast. Remove the
  guard (and add a picker + per-connection concurrency) to lift this later.
