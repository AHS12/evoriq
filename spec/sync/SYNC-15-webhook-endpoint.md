# SYNC-15 — Webhook endpoint & validation

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** CONN-01
- **Blocks:** SYNC-16, SYNC-19
- **TDR:** §21, §22, §39

## 1. Why

Webhooks are an optimization that keeps data fresh in near real time and lets us
avoid polling. They must be verified, fast, and never do heavy work inline.

## 2. Scope

**In**
- A public webhook endpoint per connection/workspace that validates authenticity
  and enqueues processing.
- Token/signature validation using the webhook's `authToken` (SYNC-19).
- Immediate `2xx` acknowledgement; no DB writes beyond enqueuing.
- Protection: rate limiting, replay tolerance, logging without secrets.

**Out**
- Creating/managing the webhook in Clockify (SYNC-19); processing the event
  (SYNC-16).

## 3. Data model
- No new table required for MVP; optionally `clockify_webhook_events` to record
  received events (can reuse `clockify_entity_changes` with source=webhook).
- The webhook's `authToken` is stored on the connection/registration record
  (SYNC-19).

## 4. Backend
- **Route** `POST /webhooks/clockify/{connection}` (`webhooks.clockify`), CSRF
  excluded, **outside** auth middleware.
- **Controller** `Webhook\ClockifyWebhookController@store`:
  1. resolve connection by path id (reject unknown/disabled);
  2. validate the auth token/signature (constant-time compare);
  3. validate payload shape minimally;
  4. dispatch `ProcessClockifyWebhookJob` (default channel);
  5. return `200` immediately (webhook senders are impatient).
- **Security:** rejects on token mismatch with `401`; rate-limit via
  `throttle`; never log the token or full secret-bearing payload; keep bodies
  for reprocessing only when safe.
- **Idempotency:** dedupe by a payload/event id when present.

## 5. Frontend / UI
- None. (SYNC-19 exposes registration + health in the UI.)

### A11y & i18n
- None.

## 6. API / routes / props
- `POST /webhooks/clockify/{connection}` (public, token-protected).

## 7. Acceptance criteria
- [ ] Valid webhooks are accepted and enqueued in < 100 ms with no inline work.
- [ ] Invalid/missing tokens are rejected `401`.
- [ ] Unknown/disabled connections are rejected.
- [ ] No secret is logged.
- [ ] `composer check` passes.

## 8. Tests
- **Feature** `ClockifyWebhookTest`: valid → 200 + job dispatched; bad token →
  401; disabled connection → 404/410; CSRF not required; body not logged.

## 9. Notes & open questions
- Clockify's exact signing header (authToken header name) must be confirmed
  during implementation; keep validation pluggable.
