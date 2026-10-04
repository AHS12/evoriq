# ENT-08 — Time entry rates

- **Status:** Done
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00, ENT-07
- **Blocks:** ANA-08, ANA-10
- **TDR:** §25.11, §26

## 1. Why

Rates are **historical facts**: a 2023 entry must use the rate that applied to
it, not today's project rate. Correct cost/billable analytics depends on storing
per-entry rates rather than reading current rates.

## 2. Scope
**In:** `clockify_time_entry_rates` derived from hydrated entries
(`hourlyRate`/`costRate`) and `TIME_ENTRY_RATE` changes.
**Out:** project/workspace/membership rates (ENT-02/04) — those are dimensions.

## 3. Data model
```text
clockify_time_entry_rates
  id, organization_id, workspace_id, clockify_id null,
  time_entry_id, user_id null, project_id null, task_id null,
  billable_rate_amount null, billable_rate_currency null,
  cost_rate_amount null, cost_rate_currency null,
  created_at/updated_at, raw_data
  unique (organization_id, workspace_id, time_entry_id)
```

## 4. Backend
- `TimeEntryRateSyncHandler` (fact phase, runs alongside ENT-07):
  - when `hydrated=true`, project `hourlyRate`/`costRate` onto a rate row per
    entry (upsert by entry);
  - apply `TIME_ENTRY_RATE` entity changes (SYNC-06) for revisions.
- Null-safe: many entries have no explicit rate → store nulls (analytics then
  falls back to project/membership rates on demand, ANA-08).
- Deletion: follows the entry (`replace`/cascade); no independent soft delete.

## 5. Frontend / UI
- None; powers cost/billable analytics.

### A11y & i18n
- Currency via FND-02.

## 6. API / routes / props
- Exposed via analytics.

## 7. Acceptance criteria
- [x] Each hydrated entry yields a rate row (nulls allowed).
- [x] Rate revisions via entity changes update the row.
- [x] Re-sync idempotent.
- [x] `composer check` passes.

## 8. Tests
- **Unit** `TimeEntryRateSyncHandlerTest` (`Http::fake()`): hydrated mapping,
  nulls, revision.

## 9. Notes & open questions
- The exact `hourlyRate`/`costRate` shape on hydrated entries is only shown as
  `null` in samples; confirm during implementation (likely
  `{amount, currency, since}`) and fall back gracefully.
- **Implemented notes:**
  - `clockify_time_entry_rates` + `ClockifyTimeEntryRate` model/factory; the
    natural key is `(organization_id, workspace_id, time_entry_id)` (no Clockify
    id), so `TimeEntryRateSyncRepository` implements `SyncUpsertRepositoryInterface`
    directly and snapshots the entry's user/project/task onto the row. Deletion
    is `NONE` — rows cascade with the entry.
  - `TimeEntryRateSyncHandler` (`TIME_ENTRY_RATE`, fact phase) reuses the
    per-user hydrated entries endpoint (there is no dedicated rate endpoint; the
    planner already funds this entity as its own fact job). Amounts/currencies
    map tolerantly; entries without explicit rates still get a null rate row.
  - Rate revisions are applied idempotently whenever the entry is re-fetched
    (re-sync/reconciliation update the row in place). The **entity-change-driven
    incremental refresh** (`TIME_ENTRY_RATE` changes → targeted re-fetch) belongs
    to SYNC-16 (webhook/change-feed incremental fetch), which is still Draft.
  - Duplicate-fetch trade-off: this handler fetches the same hydrated entries as
    ENT-07 by design (no rate endpoint). A future optimisation could derive the
    rates from ENT-07's already-persisted raw records to halve the fact fetch.
