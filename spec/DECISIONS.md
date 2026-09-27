# Decision Log

Product and architecture decisions that **amend or extend `TDR.md`**. Newest at
the bottom. Each entry is binding until superseded.

---

## DEC-001 — Evoriq is self-hosted, not a SaaS

- **Date:** 2026-09-27
- **Supersedes:** the "Lightweight SaaS" framing in TDR §1 / §6.
- **Decision:** Evoriq is a **self-hosted tool**. There is **no billing,
  subscriptions, plans, seats, or payment integration**. Confirm `/setup` is the
  install path (already built).
- **Impact:** drops any billing spec; keeps single-tenant operations.

## DEC-002 — Data-only ingestion via REST entity endpoints, not the Reports API

- **Date:** 2026-09-27
- **Resolves:** `SPIKE-01`.
- **Decision:** we synchronize **underlying Clockify entities** through the base
  REST API and build every report/aggregation/PDF ourselves. We do **not** use
  the Reports API (`/reports/*`) as an ingestion source.
- **Reasons:** TDR §9.1 (entities, not report output); Reports API is capped at
  **31 days on Free** and aggregates/loses relationships and historical rates;
  report response schema isn't even publicly documented.
- **Impact:** `ENT-07` fetches time entries **per user** via
  `GET /workspaces/{ws}/user/{user}/time-entries`; rates come from
  `hydrated=true` and `TIME_ENTRY_RATE` entity changes. Reports/PDF are our own
  (`EXP-01`).

## DEC-003 — Historical import capped at 5 years

- **Date:** 2026-09-27
- **Decision:** the historical import UI and planner offer at most **5 years
  (60 months)**. Deeper history is technically reachable but out of scope.
- **Impact:** `PIPE-09` presets become Last year / Last 2 years / Last 5 years /
  Custom (custom may still be ≤ 5 years); `SYNC-03` clamps ranges.

## DEC-004 — External object storage is deferred

- **Date:** 2026-09-27
- **Resolves:** `SPIKE-03`.
- **Decision:** artifacts/exports use the **local disk** (`config/exports.php`)
  for now. R2/S3 support is a later, swappable concern behind the existing disk
  abstraction.
- **Impact:** no R2 work now; keep export disk configurable. Revisit with
  `OPS-03`.

## DEC-005 — Organization-ready schema, single default org for now

- **Date:** 2026-09-27
- **Supersedes:** TDR §40's "no organizations" (at the DB level).
- **Decision:** model an internal `organizations` table and add
  `organization_id` to all Clockify-sourced, pipeline and analytics tables
  **now**, but operate with a single default organization and **no tenant
  scoping UI**. This is a DB-level hedge so multi-org can be added later without
  a migration cliff.
- **Mapping:** `organizations.clockify_organization_id` ↔ Clockify's
  `cakeOrganizationId` (see `spec/reference/clockify-api.md` §9). One
  organization can own multiple Clockify workspaces.
- **Impact:** see `spec/architecture/ORG-01-organization-ready-schema.md`;
  `ORG-01` blocks all entity/analytics specs.

## DEC-006 — Plan-aware rate budgeting

- **Date:** 2026-09-27
- **Resolves:** `SPIKE-05`.
- **Decision:** the rate limiter and `clockify_api_usage` accounting are
  **plan-aware**. Free workspaces: **30 requests/hour per workspace**; paid:
  **50 requests/second**. The limit is detected per connection/workspace
  (`featureSubscriptionType`/`features`) and stored on the connection; it is
  never hard-coded.
- **Impact:** `CONN-02` detects plan; `SYNC-02` tracks the correct window
  (hourly vs per-second); `SYNC-03` plans within the hourly budget; the API
  usage UI (`SYNC-17`) shows the real window. A 5-year free-plan import is a
  multi-hour, resumable job — the pipeline UI is essential.

## DEC-007 — The JS scripts are examples only

- **Date:** 2026-09-27
- **Decision:** `clockify_free_probe.js` / `clockify_monthly_reports.js` are
  reference examples, not the contract or a product feature. The verified
  contract is `spec/reference/clockify-api.md`.
- **Impact:** `SPIKE-02` resolved: per-user monthly PDF packs are **our** future
  report feature (`EXP-01`), not Clockify's export.

## DEC-008 — What "safe pipeline" means for this product

- **Date:** 2026-09-27
- **Decision:** the product's core value is a **safe, free-limit-respecting
  pipeline**: never exceed the plan's API budget, always resumable, always
  observable, never losing or duplicating data.
- **Impact:** prioritizes `SYNC-02/03/04/13/20` and the whole `PIPE` epic before
  analytics breadth.

---

## DEC-009 — Free plan keeps full history; 31-day cap is per-report only

- **Date:** 2026-09-27
- **Resolves:** `SPIKE-A`.
- **Evidence:** `clockify_free_probe.js` on a Free workspace — Reports API
  `/reports/detailed`: 31-day window HTTP 200, 32-day HTTP 400; historical
  31-day windows at 1/3/6/12/18/24/36/48/60 months all HTTP 200.
- **Conclusion:** Free workspaces retain full historical depth (≥ 60 months);
  the 31-day limit applies to a **single report interval**, not to stored
  history. This validates the 5-year import cap (`DEC-003`).
- **Caveat:** the probe exercised the **Reports API**, not the base REST entity
  endpoints (`DEC-002`). Default the planner to **31-day partitions** so the
  pipeline is correct whether or not base REST is interval-capped.
- **Impact:** `ENT-07`, `SYNC-03`.

---

## Still open

| ID         | Question | Blocks |
| ---------- | -------- | ------ |
| `SPIKE-06` | Analytics cache/invalidation strategy after incremental sync (recompute-per-sync vs on demand). | `ANA-03` |
