# ORG-01 — Organization-ready schema

- **Status:** Done
- **Epic:** architecture
- **Estimate:** M
- **Depends on:** —
- **Blocks:** CONN-01, SYNC-01, ENT-*, ANA-*, PIPE-01/02, EXP-*
- **TDR:** §25, §40 (amended by `DEC-005`)

## 1. Why

Evoriq ships as a single-organization, self-hosted tool, but the product may
later host **multiple organizations**. Adding an organization dimension after
tens of millions of synced rows exist would be an expensive, risky migration.
This spec adds the organization abstraction **at the database level now** while
the app still runs with one default organization — a cheap hedge that keeps the
door open (`DEC-005`). Clockify already gives us the natural external key:
`cakeOrganizationId` (one Cake org ↔ many workspaces).

## 2. Scope

**In**

- An `organizations` table with a single seeded default row.
- `organization_id` on every Clockify-sourced, pipeline and analytics table,
  plus existing application tables that will hold org data.
- A `BelongsToOrganization` trait (auto-set on create, opt-in query scope) and
  an `OrganizationContext` resolver.
- Mapping to Clockify's `cakeOrganizationId`.
- A documented path to real multi-org (selection UI, pivot membership) without
  schema changes to the wide tables.

**Out**

- Any multi-organization **UI**, switcher, or per-org authorization.
- Converting `users` membership to a full pivot (deferred; nullable FK now).
- Changing existing single-tenant behavior or RBAC.

## 3. Data model

### New table

```text
organizations
  id                      bigint pk
  name                    string
  slug                    string unique
  clockify_organization_id string nullable unique   # Cake organization id
  is_default              boolean default false
  settings                json nullable
  created_at / updated_at
```

Seed exactly one row: `{ name: 'Default', slug: 'default', is_default: true }`.

### Columns to add

Add `organization_id` (unsigned bigint, **nullable** to avoid a backfill cliff,
indexed, FK → `organizations.id` with `nullOnDelete`) to:

- **Application (existing):** `users`, `data_processing_jobs`, `notifications`,
  `uploads`.
- **Connection/sync (new, from day one):** `clockify_connections`,
  `clockify_workspaces`, `clockify_sync_runs`, `clockify_sync_jobs`,
  `clockify_entity_changes`, `clockify_deleted_entities`,
  `clockify_raw_records`, `clockify_api_usage`, `pipeline_events`.
- **Entities (new, from day one):** every `clockify_*` dimension/fact/context
  table (`ENT-01` … `ENT-12`, `P2-*`).
- **Analytics (new):** every `daily_*` / `monthly_*` metric table (`ANA-01`).

Compound indexes: entity uniqueness becomes `(organization_id, workspace_id,
clockify_id)` where `clockify_id` was previously globally unique — the org
dimension is part of the natural key.

> `clockify_workspaces.organization_id` is derived from the workspace's
> `cakeOrganizationId`; `clockify_connections.organization_id` is the org that
> owns the connection.

## 4. Backend

- **Model:** `App\Models\Organization` (+ factory, seeder for default org).
- **Trait:** `App\Models\Concerns\BelongsToOrganization`:
  - `organization(): BelongsTo`
  - `scopeForOrganization(Builder $q, int|string $id): Builder`
  - `scopeWithoutOrganizationScope(Builder $q): Builder`
  - on `creating`, if `organization_id` is empty, set it from
    `OrganizationContext::id()`.
- **Global scope:** the trait applies a global scope filtering
  `organization_id = OrganizationContext::id()` **only on models that use it**
  (new org-owned models). Old/existing models are untouched. Provide
  `withoutOrganizationScope()` for seeders/admin/backfill. This gives
  isolation-by-default without affecting current behavior (there is only one
  org).
- **Resolver:** `App\Support\OrganizationContext`:
  - `id(): int|string` — current org: explicit selection → user's org →
    default org; cached per request.
  - `model(): Organization`.
  - `set(?Organization)` for tests/console.
- **Binding:** bind `OrganizationContext` as a singleton in
  `AppServiceProvider`.
- **Default org resolution:** helper `Organization::default()` returning the
  `is_default` row (cached).
- **Docs to update on implementation:** `AGENTS.md` §7.11 (single-tenant) must
  be amended to describe the org-ready schema; `TDR.md` §40 carries an
  amendment note pointing here.

## 5. Frontend / UI

None. No visible change. Pages continue to operate against the default org.

### A11y & i18n

None.

## 6. API / routes / props

None. `organization_id` is never accepted from the client; it is resolved
server-side (same rule as ownership — `AGENTS.md` §7.11).

## 7. Acceptance criteria

- [x] `organizations` exists with one default row after migrate+seed.
- [x] The listed tables carry an indexed `organization_id`.
- [x] Creating an org-owned model without an explicit org sets the default org.
- [x] Queries on org-owned models are scoped to the current org; a test proves
      two orgs cannot see each other's rows.
- [x] Existing behavior (single org) is unchanged; existing feature tests pass.
- [x] `AGENTS.md` §7.11 amended; `TDR.md` §40 amendment note added.
- [x] `composer check` passes.

## 8. Tests

- **Unit** `tests/Unit/OrganizationContextTest.php`: resolution order
  (selected → user → default), memoization, `reset()`.
- **Feature** `tests/Feature/Organization/OrganizationScopeTest.php`:
  - auto-set on create;
  - global scope isolates organizations;
  - `withoutOrganizationScope()` sees all;
  - default org is unique and seeded.
- Existing tests must remain green (run the full suite).

## 9. Notes & open questions

- **Implemented deviation:** the current app models that receive the column
  (`User`, `DataProcessingJob`, `Notification`, `Upload`) also use
  `BelongsToOrganization`, so the feature is exercised today instead of waiting
  for `SYNC-01`/`ENT-01`. `OrganizationSeeder` backfills those small tables to
  the default org so existing rows stay visible; the wide Clockify/analytics
  tables are instead born with the column (no backfill cliff).
- **Re-entrancy guard:** `OrganizationContext` marks itself resolved *before*
  resolving so the `User` global scope (triggered while the auth user loads)
  cannot recurse.
- **User ↔ org:** nullable `users.organization_id` now; if true multi-org
  arrives, migrate to an `organization_user` pivot with roles. Record as a
  follow-up when multi-org is green-lit.
- **Global scope trade-off:** implicit scoping can surprise; keep the escape
  hatch well-documented and use it in seeders, backfills and cross-org admin
  tooling.
- **Natural keys:** `(organization_id, workspace_id, clockify_id)` must be
  reflected in `SYNC-08`'s upsert contract.
- Requirement to implement this **before** `SYNC-01` / `ENT-01` so wide tables
  are born with the column.
