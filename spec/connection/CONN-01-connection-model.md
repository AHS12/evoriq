# CONN-01 — Connection model & credentials

- **Status:** Draft
- **Epic:** connection
- **Estimate:** M
- **Depends on:** ORG-01, SEC-01
- **Blocks:** CONN-02, SYNC-01, SYNC-02, SYNC-19
- **TDR:** §8, §12, §16, §39

## 1. Why

Clockify access starts with a credential. We need one durable, encrypted,
org-scoped record per Clockify API key that also stores everything the pipeline
needs to behave: base URL region, subdomain, detected plan and limits, webhook
quota. This is the root of the pulling flow.

## 2. Scope

**In**
- `clockify_connections` table + `ClockifyConnection` model.
- Encrypted API key (and optional add-on token) at rest.
- Per-connection endpoint config (region/subdomain base URLs), plan/limit
  profile, status and last-verified metadata.
- Repository, service entry points, policy stub.

**Out**
- Validation/detection logic (CONN-02), UI (CONN-04/05), webhooks (SYNC-19).

## 3. Data model

Migration `create_clockify_connections_table`:

```text
clockify_connections
  id                          bigint pk
  organization_id             bigint fk -> organizations (indexed)
  name                        string                 # user label
  api_key                     text                   # encrypted cast
  addon_token                 text nullable          # encrypted cast
  region                      string default 'global' # global|eu|us|uk|au|developer
  base_url                    string                 # resolved regular API base
  reports_base_url            string                 # resolved reports base
  subdomain                   string nullable
  workspace_id                string nullable        # active Clockify workspace (clockify_id)
  feature_subscription_type   string nullable        # e.g. STANDARD_2021
  features                    json nullable
  webhook_limit               unsigned smallint nullable
  requests_per_hour           unsigned int nullable  # free plan = 30
  requests_per_second         unsigned int nullable  # paid = 50
  status                      string default 'active' # active|invalid|disabled
  last_verified_at            timestamp nullable
  last_error                  text nullable
  created_by                  bigint nullable fk users
  created_at / updated_at
```

- `ApiRegion` enum (`global|eu|us|uk|au|developer`) with `baseUrl()` /
  `reportsBaseUrl()` (see `reference/clockify-api.md` §2).
- `ConnectionStatus` enum (`active|invalid|disabled`).
- Casts: `api_key`/`addon_token` via Laravel `encrypted`; `features` array.
- **Never** serialize `api_key`/`addon_token`; `$hidden`.

## 4. Backend

- **Model** `App\Models\ClockifyConnection`: `organization()`, `workspaces()`,
  `uses org trait`; helpers `isActive()`, `isFreePlan()`, `rateProfile()`.
- **DTO** `App\DTOs\Connection\ConnectionDTO` (`fromRequest`, `toArray`).
- **Repository** `Contracts\ClockifyConnectionRepositoryInterface` +
  `Repositories\Connection\ClockifyConnectionRepository` (`create`, `update`,
  `findById`, `findActive`, `paginate`, `forOrganization`). Bind in provider.
- **Service** `Services\Connection\ClockifyConnectionService` (create/update/
  disable, credential write is transactional; audit via `AuditLogService`).
- **Policy** `ClockifyConnectionPolicy` (`viewAny/view/create/update/delete`)
  using `connection.view` / `connection.manage` (CONN-06).

## 5. Frontend / UI

None (model/service only). Props never include credentials; only id/name/region/
status/plan/workspace (`ConnectionResource`, no secrets).

### A11y & i18n
- New labels translated in all five `lang/app/*.json`.

## 6. API / routes / props
- No routes yet. `ConnectionResource` exposes `{ id, name, region, status,
  plan, workspace, last_verified_at, webhook_limit }` — never `api_key`.

## 7. Acceptance criteria
- [ ] Migrating creates the table with `organization_id` and indexes.
- [ ] Stored keys are encrypted in the DB and absent from JSON.
- [ ] Region resolves `base_url`/`reports_base_url` correctly.
- [ ] `rateProfile()` returns hourly limits for Free, per-second for paid.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ConnectionServiceUnitTest`: create/update/disable, audit calls,
  encryption round-trip, `rateProfile()` boundaries.
- **Feature** `ConnectionFeatureTest`: resource never leaks secrets; policy
  gate; org scoping.

## 9. Notes & open questions
- Confirm whether one API key can span multiple Cake orgs; if so, the
  connection↔org link may need to be 1:N or resolved per workspace.
