# ENT-15 — Clockify ↔ internal ID mapping & resolution

- **Status:** Draft
- **Epic:** entities
- **Estimate:** M
- **Depends on:** ORG-01, ENT-00
- **Blocks:** ENT-02…ENT-12
- **TDR:** §24, §25, §40

## 1. Why

Clockify payloads reference other entities by Clockify id. To insert relational
data we must resolve those external ids to our internal ids efficiently and
safely — without per-row lookups that would make imports slow.

## 2. Scope
**In**
- A resolution service mapping `(org, workspace, entityType, clockifyId)` →
  internal id, with in-memory memoization per job.
- Handling unresolved parents (out-of-order arrivals) without dropping facts.
- Consistent keying across entities.

**Out**
- Entity schemas (ENT-01…12); the upsert contract (SYNC-08).

## 3. Data model
- Every entity stores `clockify_id` and is keyed by
  `(organization_id, workspace_id, clockify_id)` (SYNC-08).
- Optional `sync_unresolved` flags/columns where a parent may arrive later.

## 4. Backend
- **Service** `Services\Sync\IdResolver`:
  - `resolve(SyncEntityType $type, string $clockifyId): ?int` — memoized
    (request/job scope), batched via a repository `pluckIdByClockifyIds()` to
    avoid N+1.
  - `prime(SyncEntityType $type, array $clockifyIds): void` for page batching.
  - `remember(SyncEntityType $type, string $clockifyId, int $internalId)` when
    an entity is upserted, so subsequent rows in the same page resolve instantly.
- **Internal ids never leave** the server; only `clockify_id` is used across the
  Clockify boundary (TDR §24).
- **Unresolved handling:** because the planner loads reference entities first
  (SYNC-03), parents normally exist. If a parent is genuinely missing, the
  fact row is stored with a null FK and flagged; a later reference sync or
  `sync:repair` (OPS-02) backfills. Never drop the fact.
- **Org/workspace scoping:** resolution is always scoped to the current org +
  workspace, preventing cross-workspace collisions.

## 5. Frontend / UI
- None.

### A11y & i18n
- None.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [ ] Resolving a page of parents uses one batched query, not N.
- [ ] Freshly upserted entities are resolvable within the same transaction.
- [ ] Unresolved parents store facts with null FK + flag and are backfilled
      later.
- [ ] Resolution is org/workspace scoped.
- [ ] `composer check` passes.

## 8. Tests
- **Unit** `IdResolverTest`: memoization, batching, remember, unresolved, scoping.
- **Feature** `OutOfOrderResolutionTest`: facts before parents → flagged, then
  repaired after the parent syncs.

## 9. Notes & open questions
- Consider a lightweight `clockify_id_map` cache table for very large imports;
  start with repository queries + memoization and measure.
