# CONN-06 — Connection policy, permissions & audit

- **Status:** Done
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
- **Registry:** the module keeps Evoriq's granular `{module}.{action}`
  permissions (`connection.view`, `connection.view.all`, `connection.create`,
  `connection.update`, `connection.delete`, `connection.credentials.update`)
  rather than a single `connection.manage` alias — see §9. They already exist in
  `config/permission-registry.php`; `permission:sync` upserts them and grants
  Super Admin.
- **Policy** `ClockifyConnectionPolicy`:
  `viewAny/view` → `connection.view` (+ `.view.all`);
  `create` → `connection.create`;
  `update/reverify/disable/enable` → `connection.update` or
  `connection.credentials.update`;
  `rotateKey` → `connection.credentials.update` (credential-only);
  `delete` → `connection.delete`.
- **Controllers** call `Gate::authorize(...)` per action (`update`, `reverify`,
  `rotateKey`, `disable`, `enable`); use the `add-permission` skill.
- **Audit:** channel `security` for credential actions (rotate/disable/enable)
  and `domain` for create/update/verify/select; actor recorded; keys redacted
  centrally (`config/audit.php` `redacted_attributes` includes `api_key`;
  `addon_token` matches `*token`).
- **Scheduler guard:** `ClockifyConnectionRepository::allActive()` returns only
  `status = active` connections, so disabled/invalid connections are never
  synced.

## 5. Frontend / UI
- Navigation and actions gated by `useCan('connection.view'|'connection.manage')`.

### A11y & i18n
- Permission labels translated.

## 6. API / routes / props
- Shared `can` props already exist; add the new keys.

## 7. Acceptance criteria
- [x] Users without the matching permission cannot create/rotate/disable
      (rotation specifically needs `connection.credentials.update`).
- [x] Every lifecycle action writes an audit row with an actor.
- [x] No audit or log row contains a key/token value.
- [x] Disabled connections are skipped by the scheduler (`allActive`).
- [x] `composer check` passes.

## 8. Tests
- **Feature** `ConnectionPermissionTest`: 403 for each protected action without
  the permission; credential-only rotation; redaction asserted; scheduler guard.
- `ConnectionFeatureTest` extends the policy matrix to the new abilities.

## 9. Notes & open questions
- Resolved: `connection.view` follows the existing RBAC conventions (granted to
  Member and Admin via `RoleSeeder`); the spec's `connection.manage` maps to the
  granular `connection.update` / `connection.delete` / `connection.create` /
  `connection.credentials.update` set instead of introducing a broad alias.
- Resolved: key rotation is a credential action and requires
  `connection.credentials.update`, which `RoleSeeder` deliberately withholds from
  the Admin role (Super Admin only by default).
