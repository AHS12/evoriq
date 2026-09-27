# SYNC-19 — Webhook registration & lifecycle

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-15, CONN-01
- **Blocks:** SYNC-16
- **TDR:** §21, §22

## 1. Why

Receiving webhooks (SYNC-15) requires first creating them in Clockify, rotating
their tokens, and knowing whether they are healthy — within the plan's webhook
quota (Free = 3). This spec owns that lifecycle.

## 2. Scope

**In**
- Create/update/delete webhooks via the Clockify API for a connection/workspace.
- Store each registration (id, url, events, token, enabled) per connection.
- Token rotation (`PATCH .../token`) and signature validation handoff to
  SYNC-15.
- Delivery health via webhook logs/statuses (`SUCCEEDED|RETRYING|FAILED`,
  `retryCount`).
- Quota awareness (Free 3) and prioritization when over quota.

**Out**
- The receiving endpoint (SYNC-15) and event processing (SYNC-16).

## 3. Data model

```text
clockify_webhooks
  id, organization_id, connection_id, workspace_id
  clockify_id         string
  url                 string
  webhook_event       string            # one event per registration
  trigger_source_type string
  trigger_source      json
  auth_token          text              # encrypted
  enabled             boolean
  last_status         string nullable   # SUCCEEDED|RETRYING|FAILED
  last_delivery_at    timestamp nullable
  retry_count         int nullable
  raw_data            json nullable
  created_at/updated_at
  unique (organization_id, connection_id, clockify_id)
```

## 4. Backend
- **Service** `Services\Clockify\ClockifyWebhookService`:
  - `register(connection, event, url)`, `update`, `delete`, `rotateToken`.
  - `health(connection)` → fetch logs/statuses, summarize.
- **Repository** `ClockifyWebhookRepository`; model `ClockifyWebhook` (org
  trait, encrypted token).
- **Registration policy:** choose a covered event set for MVP (time entry
  created/updated/deleted/restored, project/task/tag/client changes) within the
  quota; if over quota, prioritize time entries.
- **Routes/UI:** a connections → webhooks section (manage + health), gated by
  `connection.manage`; registration is opt-in and reversible.
- **Config:** enable webhooks per connection (`webhook_enabled`), default on
  when quota allows.
- **Audit:** `webhook.registered|updated|deleted|token_rotated`.

## 5. Frontend / UI
- Webhook card in connection settings: registered events, status badge
  (healthy/retrying/failing), last delivery, "Rotate token", "Disable".
- Warning when the plan quota is reached; explanation that webhooks are an
  optimization.

### A11y & i18n
- Translated labels; status by text+icon.

## 6. API / routes / props
- `connections.webhooks.index|store|destroy|rotate`
  (`can:connection.manage`).

## 7. Acceptance criteria
- [ ] Registering creates the webhook in Clockify and stores its token
      encrypted.
- [ ] Over-quota registration is prevented with a clear plan message.
- [ ] Health reflects logs/statuses and surfaces failures.
- [ ] Token rotation invalidates the old token and updates validation.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ClockifyWebhookServiceTest` (`Http::fake()`): register/update/delete,
  quota guard, rotate, health summary.
- **Feature** `WebhookRegistrationFeatureTest`: UI endpoints + authorization +
  audit.

## 9. Notes & open questions
- Confirm the exact webhook auth header name/format for validation in SYNC-15;
  keep the validator pluggable.
