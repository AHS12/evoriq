# SYNC-06 — Entity Changes adapter

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-01
- **Blocks:** SYNC-07, SYNC-10, SYNC-11, SYNC-16
- **TDR:** §20, §20.1, §20.3

## 1. Why

Incremental sync is what makes daily sync cheap and correctness high. Clockify's
**experimental** Entity Changes API (`/entities/created|updated|deleted`) is the
mechanism, but it is experimental and has documented caveats — so it must sit
behind a swappable adapter.

## 2. Scope

**In**
- A `ChangeFeed` interface with a Clockify Entity Changes implementation.
- `created`/`updated`/`deleted` retrieval with `type`, `start`, `end`, `page`,
  `limit`.
- Normalizing results into `clockify_entity_changes`.
- Honoring documented caveats (updated excludes create+update; deleted ~1 min
  delay; created+deleted in range omitted).

**Out**
- Applying changes to entities (SYNC-07 + ENT-*); scheduling (SYNC-10/11).

## 3. Data model
- `clockify_entity_changes` (SYNC-01).

## 4. Backend
- **Contract** `App\Services\Sync\Contracts\ChangeFeed`:
  - `since(workspace, CarbonInterface $from, CarbonInterface $to, SyncEntityType $type): EntityChangePage`
  - each page yields `EntityChangeDTO { entityType, clockifyId, changeType,
    sourceAt, raw }`.
- **Implementation** `Services\Clockify\ChangeFeed\ClockifyEntityChangesFeed`
  (isolated; uses `ClockifyClient` + rate limiter):
  - calls `/entities/created`, `/entities/updated`, `/entities/deleted`
    with `type`, `start`, `end`, `page` (0-based), `limit`;
  - tolerates the two documented `deleted` response shapes (`{response:[…]}` vs
    bare array);
  - paginates via the endpoint's own `page`/`limit` (not `Last-Page`).
- **Recorder** `EntityChangeRecorder`: upserts `clockify_entity_changes`
  (org/workspace/entity/clockify_id/change_type/source_at), sets `detected_at`.
- **Guarded:** feature flag / config (`clockify.change_feed.enabled`) so a
  Clockify change can disable it without code deploy.
- **Checkpoint:** the "last successful change scan time" is stored on the
  connection/workspace (SYNC-11 uses it).

## 5. Frontend / UI
- None. (`PIPE-10` may surface change-feed health later.)

### A11y & i18n
- None.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [ ] Created/updated/deleted changes are fetched and recorded for a range.
- [ ] Both documented `deleted` response shapes are handled.
- [ ] Pagination by `page`/`limit` works and stops at the end.
- [ ] The adapter can be replaced without touching callers.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `ClockifyEntityChangesFeedTest` with fixtures for the two deleted
  shapes, multi-page, and each type.
- **Feature** `EntityChangeRecorderTest`: records are upserted and idempotent.

## 9. Notes & open questions
- Only `TIME_ENTRY`, `TIME_ENTRY_RATE`, `TIME_ENTRY_CUSTOM_FIELD_VALUE` are
  documented stable; treat others as best-effort and reconcile via snapshots
  (SYNC-10) when uncertain.
