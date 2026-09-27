# SYNC-11 — Daily automatic synchronization

- **Status:** Draft
- **Epic:** sync
- **Estimate:** M
- **Depends on:** SYNC-09, SYNC-06
- **Blocks:** SYNC-12, SYNC-18
- **TDR:** §15, §18

## 1. Why

Once imported, data must stay fresh without the user thinking about it. A daily
incremental sync (default 01:00, configurable) keeps our dataset current while
respecting the API budget. A one-day freshness delay is acceptable for MVP.

## 2. Scope

**In**
- A scheduled daily incremental sync using the Entity Changes feed from the last
  checkpoint.
- Configurable time/enable via CONN-09.
- A stored freshness checkpoint per connection/workspace (`last_synced_at`,
  `data_through`).
- Per-connection time zone for scheduling.

**Out**
- Manual sync (SYNC-12), reconciliation (SYNC-10), webhooks (SYNC-15/16).

## 3. Data model
- `SyncRun(mode=incremental, trigger=scheduled)`.
- Freshness fields on the connection/workspace: `last_synced_at`,
  `last_change_scan_at`, `data_through`.

## 4. Backend
- **Command** `sync:daily` scheduled in `routes/console.php`
  (`->dailyAt(setting('clockify_daily_sync_time'))`, `withoutOverlapping`,
  `onOneServer`).
- **Service** `DailySyncService::run()`:
  1. Skip disabled/inactive connections;
  2. compute `from = last_change_scan_at - overlap` (overlap buffer to absorb
     the ~1-min deletion delay and clock skew);
  3. fetch created/updated/deleted via SYNC-06 over `from..now`;
  4. build jobs and start a `SyncRun` via SYNC-09 (priority normal);
  5. advance `last_change_scan_at`, set `data_through`, `last_synced_at`.
- **Low-budget deferral:** if budget exhausted, defer and reschedule (SYNC-20).
- **Audit:** `sync.daily_started`.

## 5. Frontend / UI
- Freshness surfaced on the dashboard (`DASH-05`) via SYNC-18: "last synced",
  "data through", "next sync".

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- Consumed by SYNC-18.

## 7. Acceptance criteria
- [ ] A daily run picks up new/updated/deleted entries since the last checkpoint.
- [ ] Checkpoint advances only after successful application; failures do not
      lose changes (overlap re-reads them).
- [ ] Changing sync time in settings reschedules the job.
- [ ] Disabled connections are skipped.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `DailySyncServiceTest`: checkpoint overlap, deferral, skip-disabled.
- **Feature** `DailySyncFeatureTest`: seeded changes are applied after the run;
  `input('last_change_scan_at')` advanced.

## 9. Notes & open questions
- Overlap buffer default (e.g. 10 minutes) is configurable; larger = safer but
  more requests.
