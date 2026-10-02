# CONN-07 — Clockify API error taxonomy & messaging

- **Status:** Done
- **Epic:** connection
- **Estimate:** S
- **Depends on:** CONN-02
- **Blocks:** PIPE-05, PIPE-07, SYNC-13, SYNC-18
- **TDR:** §38, §41

## 1. Why

"Something went wrong" destroys trust in a background pipeline. Users must see
*what* failed, *whether it is safe*, and *what to do* — retry, reconnect, or
wait. One central mapper keeps error messaging consistent across connect, sync
and the pipeline UI.

## 2. Scope

**In**
- Extend `ApiErrorCode` with the full set of Clockify failures.
- A central `ClockifyErrorMapper` (exception/response → `ApiErrorCode`).
- Friendly `label()`, `hint()`, `action()` on each code.
- Surfacing `Retry-After` / rate-limit resets.

**Out**
- The pipeline's own failure taxonomy `PipelineFailureReason` (PIPE-02) — this
  mapper feeds it.

## 3. Data model
- None.

## 4. Backend
- **Enum** `App\Enums\ApiErrorCode` additions:
  `CLOCKIFY_AUTHENTICATION_FAILED`, `CLOCKIFY_FORBIDDEN`,
  `CLOCKIFY_NOT_FOUND`, `CLOCKIFY_RATE_LIMITED`, `CLOCKIFY_API_ERROR`,
  `CLOCKIFY_API_UNAVAILABLE`, `CLOCKIFY_NETWORK_ERROR`,
  `CLOCKIFY_INVALID_RESPONSE`, `CLOCKIFY_SUBSCRIPTION_REQUIRED`,
  `CLOCKIFY_UNKNOWN`.
- Each case: `label()` (title), `hint()` (what happened + what to do),
  `action()` ∈ `retry|reconnect|wait|contact_support`, all `__()`-wrapped.
- **Mapper** `Services\Clockify\ClockifyErrorMapper::fromResponse()/
  fromThrowable()`:
  - `401` → authentication; `403` → forbidden (plan/feature);
  - `400` → api_error (surface Clockify message when safe);
  - `404` → not_found; `429`/"Too many requests" → rate_limited (`retry_after`);
  - `5xx`/timeout/connection → unavailable/network; invalid JSON →
    invalid_response.
- **Message safety:** never surface raw stack traces or keys; map to codes and
  keep technical detail for developers only (PIPE-06 Advanced).

## 5. Frontend / UI
- Components render `action` to choose the primary button (PIPE-07):
  `retry` → Retry, `reconnect` → Reconnect Clockify, `wait` → retry-later with
  countdown, `contact_support` → support hint.
- No hard-coded error strings in the frontend.

### A11y & i18n
- Codes' labels/hints come from PHP and render via `t()`.

## 6. API / routes / props
- Error codes appear on `ApiException`/`PipelineRunResource.failure.reason`.

## 7. Acceptance criteria
- [x] Every documented Clockify failure maps to exactly one `ApiErrorCode`.
- [x] `action()` returns a usable primary action.
- [x] Rate-limit errors expose the reset/`Retry-After` when available.
- [x] No raw API error text reaches normal users.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `ClockifyErrorMapperTest`: table-driven across status codes, network
  exceptions, malformed JSON, rate limit with/without `Retry-After`.

## 9. Notes & open questions
- Clockify's error body shape is inconsistent; parse defensively (string vs
  JSON) and fall back to the status code.
