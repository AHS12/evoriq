# CONN-02 — Connection validation & capability detection

- **Status:** Done
- **Epic:** connection
- **Estimate:** M
- **Depends on:** CONN-01
- **Blocks:** CONN-03, CONN-04, SYNC-02, SYNC-03
- **TDR:** §8, §12, §16, §39

## 1. Why

Before we can plan a safe import we must know three things: is the key valid,
what workspace/org does it reach, and **what is the API budget** (Free = 30/h vs
paid = 50/s). This spec turns a pasted key into a verified connection with a
detected capability profile.

## 2. Scope

**In**
- `ConnectionService::verify()` / `connect()`: probe `GET /v1/user`, list
  workspaces, detect `cakeOrganizationId`, `featureSubscriptionType`,
  `features`, subdomain and webhook quota.
- Region/subdomain detection and base-URL resolution.
- Mapping HTTP errors to `ApiErrorCode` (feeds CONN-07).
- Persisting the verified profile on the connection (CONN-01) and upserting
  workspaces (CONN-03).
- A plan → rate-profile resolver.

**Out**
- UI (CONN-04), settings (CONN-09), actual data sync (SYNC).

## 3. Data model
- No new tables; writes `clockify_connections` (CONN-01) + `clockify_workspaces`
  (CONN-03).

## 4. Backend
- **Service** `Services\Connection\ConnectionVerifier`:
  1. `GET /v1/user` with the key (via `ClockifyClient::forCredentials`) →
     `{ id, email, name, activeWorkspace, defaultWorkspace }`.
  2. `GET /v1/workspaces` → list with `cakeOrganizationId`,
     `featureSubscriptionType`, `features`, `subdomain`.
  3. Build the capability profile; resolve `region`/`base_url` from the
     connection's region setting (fallback `global`).
  4. Persist (`status=active`, `last_verified_at=now`) or (`status=invalid`,
     `last_error`).
- **Plan resolver** `PlanProfile`: maps `featureSubscriptionType`/`features` to
  `{ plan, requests_per_hour?, requests_per_second?, webhook_limit }`.
  Unknown → conservative (treat as free hourly unless `features` clearly paid).
- **Error mapper** `Services\Clockify\ClockifyErrorMapper`:
  `401/403 → CLOCKIFY_AUTHENTICATION_FAILED`, `400 → CLOCKIFY_API_ERROR`,
  `429`/"Too many requests" → `CLOCKIFY_RATE_LIMITED` (with `Retry-After` when
  present), `5xx/network → CLOCKIFY_API_UNAVAILABLE`.
- **Repository:** reuse `ClockifyConnectionRepository` (CONN-01).
- **Audit:** `connection.verified` / `connection.validation_failed` via
  `AuditLogService` (never log the key).

## 5. Frontend / UI
- None directly; returns a typed result the connect UI (CONN-04) renders:
  `{ ok, error?, profile, workspaces[] }`.

### A11y & i18n
- Error titles/hints come from `ApiErrorCode::label()/hint()` (`__()`), rendered
  via `t()`.

## 6. API / routes / props
- Internal service call. Exposed to the UI through `POST /connections/verify`
  (CONN-04).

## 7. Acceptance criteria
- [x] A valid key yields a verified profile and upserts its workspaces.
- [x] A bad key marks the connection invalid with a mapped error, no key leaked.
- [x] Free workspaces resolve to `requests_per_hour = 30`; paid to
      `requests_per_second = 50`.
- [x] Regional/subdomain base URLs resolve correctly.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `ConnectionVerifierTest` with `Http::fake()`: success, 401, 429,
  unknown plan fallback, region resolution, workspace upsert.
- **Feature** `ConnectionVerifyFeatureTest`: endpoint returns typed result and
  never echoes the key.

## 9. Notes & open questions
- `features` enum is not fully documented; keep the resolver rule-based and
  overridable per connection (CONN-09) so a misdetected Free limit can be fixed.
- Detect subdomain-specific keys: if `/user` works on global but `/workspaces`
  fails, retry with the subdomain base URL.
