# CONN-06 — Connection policy, permissions & audit

- **Status:** Draft
- **Epic:** connection
- **Estimate:** S
- **Depends on:** CONN-01
- **Blocks:** CONN-04, CONN-05
- **TDR:** §39, §40

## 1. Why

Credentials are the most sensitive object in the system. Every action must be
authorized and recorded, and no role should see more than it needs.

## 2. Scope

**In**
- Permissions `connection.view` and `connection.manage` in the registry.
- `ClockifyConnectionPolicy` enforcing them on every action.
- Audit coverage for all connection lifecycle events.
- Redaction assurance: keys never appear in audit/logs.

**Out**
- The connection UI (CONN-04/05) and encryption (CONN-08/SEC-01).

## 3. Data model
- None.

## 4. Backend
- **Registry:** add `connection.view` ("View Clockify connections") and ensure
  `connection.manage` ("Manage Clockify connections") exist in
  `config/permission-registry.php`; run `permission:sync`; grant Super Admin.
- **Policy** `ClockifyConnectionPolicy`:
  `viewAny/view` → `connection.view`; `create/update/delete/reverify/rotateKey/
  disable/enable` → `connection.manage`.
- **Controllers** call `Gate::authorize(...)`; use the `add-permission` skill.
- **Audit:** channel `security` for credential actions (rotate/disable/
  disconnect) and `domain` for verify/select; actor recorded; keys redacted
  centrally (`config/audit.php` `redacted_attributes` includes `api_key`,
  `addon_token`).
- **Scheduler guard:** the daily/reconciliation scheduler selects only
  connections where `status = active`.

## 5. Frontend / UI
- Navigation and actions gated by `useCan('connection.view'|'connection.manage')`.

### A11y & i18n
- Permission labels translated.

## 6. API / routes / props
- Shared `can` props already exist; add the new keys.

## 7. Acceptance criteria
- [ ] Users without `connection.manage` cannot create/rotate/disconnect.
- [ ] Every lifecycle action writes an audit row with an actor.
- [ ] No audit or log row contains a key/token value.
- [ ] Disabled connections are skipped by the scheduler.
- [ ] `composer check` passes.

## 8. Tests
- **Feature** `ConnectionPermissionTest`: 403 for each protected action without
  the permission; audit rows created on success; redaction asserted.

## 9. Notes & open questions
- Decide whether `connection.view` should be part of a default admin role only;
  follow the existing RBAC conventions.
