# ENT-13 — Deletions & restores across entities

- **Status:** Draft
- **Epic:** entities
- **Estimate:** M
- **Depends on:** SYNC-07, ENT-01…ENT-12
- **Blocks:** —
- **TDR:** §20.2, §24, §41

## 1. Why

Correctness of historical reports depends on applying Clockify deletions and
restores consistently. SYNC-07 defines the mechanism; this spec pins the
**per-entity policy** and the cross-entity cascade so nothing diverges.

## 2. Scope
**In**
- The deletion policy table for every entity.
- Cascade rules (project deleted, client deleted, user removed, tag deleted).
- Restore behaviour.
- Aggregation exclusion rules (which deleted rows still count).

**Out**
- The feed/adapter (SYNC-06) and the applier mechanism (SYNC-07).

## 3. Data model
- `clockify_deleted_entities` (SYNC-01) + `deleted_at` on entity tables.

## 4. Backend

**Policy**

| Entity | On delete | Historical impact |
| --- | --- | --- |
| Workspace | mark inactive | stop syncing |
| User | soft `deleted_at` | entries still attribute to the name |
| Membership | end/remove | rates history retained separately |
| Client | soft `deleted_at` | projects/entries keep client name |
| Project | soft `deleted_at` | entries keep project; excluded from active lists |
| Project member | remove row | historical rates retained on entry rates |
| Task | soft `deleted_at` | entries keep task |
| Tag | soft `deleted_at` + remove join rows | tag removed from entries |
| Time entry | soft `deleted_at` | excluded from analytics unless include-deleted |
| Time entry rate | follow entry | — |
| Custom field | soft `deleted_at` | values retained but hidden |
| CF value | remove row | — |
| User group | soft `deleted_at` + remove members | team dimension excludes it |

- **Cascade:** deleting a project does **not** delete its time entries; it
  soft-deletes the project so entries retain an FK and reports keep names.
  Deleting a client soft-deletes it; projects remain.
- **Restore:** a later `created`/`updated` for the same `clockify_id` clears
  `deleted_at` and re-applies the payload (SYNC-07).
- **Aggregations:** ANA excludes rows with `deleted_at` by default; a
  "historical fidelity" mode can include them (documented in ANA-05).
- **Idempotency:** applying the same deletion twice is a no-op.

## 5. Frontend / UI
- Deleted counts surface in run counters (PIPE-02).

### A11y & i18n
- Labels translated.

## 6. API / routes / props
- None.

## 7. Acceptance criteria
- [ ] Every entity follows the policy table.
- [ ] No cascade destroys historical facts.
- [ ] Delete→restore converges; re-applying is a no-op.
- [ ] Aggregations exclude soft-deleted rows by default.
- [ ] `composer check` passes.

## 8. Tests
- **Feature** `EntityDeletionPolicyTest`: per-entity delete/restore; historical
  name resolution; aggregation exclusion.

## 9. Notes & open questions
- Decide whether a deleted time entry should always be excluded from totals;
  Clockify treats deletion as gone, so default exclude.
