# CONN-03 — Workspace discovery & selection

- **Status:** Draft
- **Epic:** connection
- **Estimate:** S
- **Depends on:** CONN-02
- **Blocks:** SYNC-03, SYNC-09, ENT-01
- **TDR:** §9.3, §25.2, §40

## 1. Why

A Clockify API key can reach several workspaces, each with its own time zone,
currency and history. The pipeline is scoped to a workspace, so the user must
pick one (and be able to switch).

## 2. Scope

**In**
- `clockify_workspaces` dimension table (org-scoped).
- Discovering workspaces during verify (CONN-02) and persisting them.
- Selecting/switching the **active workspace** per connection (setting).
- A minimal workspace picker surfaced in the connect flow (CONN-04).

**Out**
- Syncing workspace internals beyond the core fields (ENT-01 expands).
- Concurrent multi-workspace import (one active at a time for MVP).

## 3. Data model

```text
clockify_workspaces
  id                        bigint pk
  organization_id           bigint fk organizations (indexed)
  connection_id             bigint fk clockify_connections (indexed)
  clockify_id               string            # workspace id
  name                      string
  subdomain                 string nullable
  currency                  string nullable
  time_zone                 string nullable
  week_start                string nullable
  feature_subscription_type string nullable
  features                  json nullable
  active                    boolean default true
  raw_data                  json nullable
  created_at/updated_at/synced_at
  unique (organization_id, clockify_id)
```

## 4. Backend
- **Model** `ClockifyWorkspace` (org trait) + factory.
- **Repository** `ClockifyWorkspaceRepository` (`upsertMany`, `forConnection`,
  `findByClockifyId`); bind.
- **Service** `WorkspaceService::syncFromConnection(connection, payload)` upserts
  discovered workspaces; `selectActive($connection, $clockifyId)`.
- **Setting:** `active_workspace_id` per connection (CONN-09) or a column on the
  connection (`workspace_id`); store both clockify id and internal id.
- **Audit:** `workspace.selected`.

## 5. Frontend / UI
- Workspace picker step in CONN-04: radio list (name, time zone, currency,
  plan). Selecting updates the active workspace and continues.
- Connection settings (CONN-05) allows switching later.

### A11y & i18n
- Radio group with labels; translated strings.

## 6. API / routes / props
- `PUT /connections/{connection}/workspace` (`connections.workspace.update`) →
  sets active workspace.

## 7. Acceptance criteria
- [ ] Verify discovers and stores all workspaces for a key.
- [ ] Exactly one active workspace per connection; switching persists.
- [ ] Re-verify updates workspace fields without duplicating rows.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `WorkspaceServiceUnitTest`: idempotent upsert, active selection.
- **Feature** `WorkspaceSelectionFeatureTest`: switch endpoint + authorization.

## 9. Notes & open questions
- Multiple simultaneous workspaces may be desirable later; the schema already
  allows it (many workspaces per connection), only the "one active" rule is
  product-level.
