# SYNC-16 — Webhook → incremental fetch

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-15, SYNC-06
- **Blocks:** SYNC-18
- **TDR:** §21, §22

## 1. Why

Webhooks tell us *something changed*; they rarely carry the full record. To stay
correct we convert the notification into a targeted, rate-limited fetch of the
affected entity and apply it idempotently.

## 2. Scope

**In**
- A queued job that maps a webhook event to the affected entity + id.
- A targeted fetch/upsert (or deletion) through the shared engine.
- Coalescing/debouncing bursts (e.g. timer stop floods).
- Sharing the rate limiter/budget with all other sync work.

**Out**
- Receiving/validating the webhook (SYNC-15); registration (SYNC-19).

## 3. Data model
- Optionally record the event in `clockify_entity_changes` (source `webhook`) so
  processing is decoupled and replayable.

## 4. Backend
- **Job** `App\Jobs\Sync\ProcessClockifyWebhookJob` (default channel, thin).
- **Service** `Services\Sync\WebhookProcessor::handle(event)`:
  - map `webhookEvent` → `SyncEntityType` + affected id(s)
    (`NEW_TIME_ENTRY|TIME_ENTRY_UPDATED|TIME_ENTRY_DELETED|…`, project/task/tag/
    client events);
  - record an entity change row;
  - enqueue a **high-priority** targeted `SyncEntityJob` (or the deletion
    applier) for just that record;
  - for deletion events, apply SYNC-07 semantics.
- **Coalescing:** if the same entity is fetched repeatedly within a short
  window, collapse to one fetch (cache/`remember` or a dedupe key).
- **Budget:** uses `ApiUsageService`; on exhaustion, defer (SYNC-20).
- **Fallback:** if webhooks are disabled/over quota, the daily/reconciliation
  jobs (SYNC-10/11) still converge — webhooks are an optimization only.

## 5. Frontend / UI
- Webhook-triggered syncs appear in the pipeline UI like any run.

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [ ] A time-entry webhook results in that entry being fetched and upserted.
- [ ] A delete webhook applies deletion semantics.
- [ ] Bursts are coalesced (no N fetches for N rapid events on one entity).
- [ ] Exhausted budget defers rather than dropping events.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `WebhookProcessorTest`: event→entity mapping, coalescing, deletion
  path, deferral.
- **Feature** `WebhookIncrementalTest` with `Http::fake()`: webhook → entry
  updated in DB.

## 9. Notes & open questions
- Coalescing window default (e.g. 5s) configurable; balance freshness vs
  requests.
