# CONN-04 — Connect flow UI

- **Status:** Done
- **Epic:** connection
- **Estimate:** M
- **Depends on:** CONN-02, CONN-03, CONN-07
- **Blocks:** PIPE-09, ENT-14
- **TDR:** §34, §39

## 1. Why

Connecting a Clockify key is the first run moment. It must be obvious, safe
(never echo the key), and immediately tell the user what we found — workspace,
plan and budget — before they choose a history range.

## 2. Scope

**In**
- A connect wizard step: enter API key (+ region), verify, pick workspace,
  see detected plan/limits.
- Clear, translated error feedback with a retry.
- Entry points: first-run onboarding and "Add connection" from settings.

**Out**
- Range selection and import (PIPE-09 / ENT-14).
- Key rotation/disconnect (CONN-05).

## 3. Data model
- None.

## 4. Backend
- **Routes** (`auth`, `verified`, `can:connection.manage`):
  - `GET /connections` (`connections.index`) — list + form shell.
  - `POST /connections/verify` (`connections.verify`) → `ConnectionVerifyResult`
    (CONN-02) — no persistence beyond profile/workspaces.
  - `POST /connections` (`connections.store`) — persist the chosen connection
    (CONN-01) with the active workspace.
- **Controller** `ConnectionController@index|verify|store` — thin, FormRequest
  validated (`StoreConnectionRequest`, `VerifyConnectionRequest`).
- **Resource** `ConnectionResource`, `WorkspaceOptionResource`.

## 5. Frontend / UI

**Files**
```text
resources/js/pages/connections/index.tsx
resources/js/components/connection/connect-form.tsx
resources/js/components/connection/connection-result.tsx
resources/js/components/connection/workspace-picker.tsx
resources/js/lib/schemas/connection.ts
resources/js/types/connection.ts
```

**Experience**
1. **Key step** — password-style input for the API key (paste once, never
   re-rendered), region select (default Global), a short "where do I find my
   key?" help link, and a Verify button (loading spinner).
2. **Result step** — on success: connected account name/email (masked email),
   workspace radio picker (name, tz, currency, plan badge), and a budget line
   ("Free plan — 30 requests/hour" or "Paid — 50 requests/second").
   On failure: `InlineAlert` with the mapped `ApiErrorCode` title/hint and a
   Retry button.
3. **Confirm** — "Use this connection" persists and routes onward (to PIPE-09
   or connections list).

**States:** idle, verifying, invalid-key, rate-limited (with reset hint),
network error, no-workspaces, success.

### A11y & i18n
- `noValidate`, errors via `<InputError>`; labels tied to inputs.
- Key input `autoComplete="off"`, `type="password"`, `spellCheck={false}`.
- All copy translated in five `lang/app/*.json`.

## 6. API / routes / props
- `connections.index` props: `connections`, `regions`.
- `connections.verify` → `{ ok, profile, workspaces, error }`.
- `connections.store` → redirect.

## 7. Acceptance criteria
- [x] A user can paste a key, verify, pick a workspace and save in one flow.
- [x] The key is never rendered after submission and never logged.
- [x] Errors are specific and translated (auth vs rate-limit vs network).
- [x] Detected plan/limits are shown before saving.
- [x] `composer check` passes.

## 8. Tests
- **Feature** `ConnectionFlowFeatureTest`: index renders; verify validates input;
  store persists with active workspace; authorization enforced; response never
  contains the key.

## 9. Notes & open questions
- Consider a QR/link helper to Clockify's profile page; keep external and
  optional.
