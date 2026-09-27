# Evoriq Specs

This folder is the **execution backlog** for building Evoriq into a polished
product. `TDR.md` remains the source of truth for product and architecture;
`AGENTS.md` remains the source of truth for how we build (Service–Repository,
frontend conventions, quality gate). The specs here translate those documents
into **bite-sized, individually shippable units of work**.

> If a spec and `AGENTS.md` disagree, `AGENTS.md` wins. If a spec and `TDR.md`
> disagree, the spec should be fixed.

## 1. What a spec is

One spec is **one small, reviewable change** — typically **S (≤ 0.5 day)**,
**M (≤ 1.5 days)** or **L (≤ 3 days)** of focused work. If a spec grows past
`L`, split it. A spec must be finishable and verifiable on its own: its
acceptance criteria are concrete and its tests are named.

A spec is **not** a phase, an epic or a milestone. Epics and phases only exist
in `ROADMAP.md` as grouping.

## 2. Folder layout

```text
spec/
├── README.md                     # this file
├── ROADMAP.md                    # full phased backlog (every spec, one line each)
├── DECISIONS.md                  # binding decisions that amend TDR.md
├── reference/                    # verified external contracts
│   └── clockify-api.md           # the Clockify API facts we build on
├── architecture/                 # foundational schema/infra decisions
│   └── ORG-01-organization-ready-schema.md
├── foundations/                  # cross-cutting UI/craft primitives
│   ├── FND-01-motion-and-feedback.md
│   ├── FND-02-formatting-utilities.md
│   └── FND-03-date-range-picker.md
├── pipeline/                     # the "Pipeline Experience" epic (flagship)
│   ├── PIPE-01-event-stream.md
│   ├── PIPE-02-run-aggregation.md
│   ├── PIPE-03-live-polling.md
│   ├── PIPE-04-timeline-primitive.md
│   ├── PIPE-05-run-timeline-ui.md
│   ├── PIPE-06-run-detail-inspector.md
│   ├── PIPE-07-retry-resume-cancel.md
│   ├── PIPE-08-global-pipeline-indicator.md
│   ├── PIPE-09-import-progress-experience.md
│   ├── PIPE-10-sync-health-observability.md
│   ├── PIPE-11-pipeline-notifications.md
│   └── PIPE-12-i18n-states-retention.md
├── connection/                   # connect via API key → workspace
│   └── CONN-01 … CONN-09
├── sync/                         # the pulling pipeline (plan → schedule → execute → resume)
│   └── SYNC-01 … SYNC-20
└── entities/                     # per-entity ingestion (insert into our DB)
    ├── ENT-00-ingestion-framework.md
    └── ENT-01 … ENT-15
```

New epics get a new folder (`connection/`, `sync/`, `entities/`, `analytics/`,
`reports/`, …) and IDs continue from their prefix. File name is
`{ID}-{kebab-title}.md`.

## 3. Spec template

Every spec uses this exact structure. Keep sections short; link instead of
copying.

```md
# {ID} — {Title}

- **Status:** Draft | Ready | In Progress | Blocked | Done
- **Epic:** {folder}
- **Estimate:** S | M | L
- **Depends on:** {IDs or "—"}
- **Blocks:** {IDs or "—"}
- **TDR:** {§refs}

## 1. Why
One paragraph. The user/business outcome, not the implementation.

## 2. Scope
**In**
- …
**Out**
- …

## 3. Data model
Migrations/columns/casts/enums, or "none".

## 4. Backend
Layers to add/change (model, enum, DTO, repository, service, request,
resource, policy, job, command, route). Keep to the Service–Repository rules.

## 5. Frontend / UI
**Experience** — what the user sees and does.
**States** — loading, empty, error, partial, live.
**A11y & i18n** — semantics, keyboard, focus, locale keys.

## 6. API / routes / props
Endpoints, Inertia props, Resource shape.

## 7. Acceptance criteria
- [ ] Testable, user-observable statements.

## 8. Tests
Unit (service) + feature (controller/page) names, and what they assert.

## 9. Notes & open questions
Decisions still open, risks, follow-ups.
```

## 4. Status legend

| Status        | Meaning                                                    |
| ------------- | ---------------------------------------------------------- |
| `Draft`       | Written but not reviewed; may change.                      |
| `Ready`       | Reviewed; dependencies satisfied; can be started.          |
| `In Progress` | Actively being built. Exactly one owner.                   |
| `Blocked`     | Waiting on a dependency or decision (state it).            |
| `Done`        | Merged, tests green, `composer check` passes.              |

Keep `ROADMAP.md`'s status column in sync when a spec moves.

## 5. Definition of Done

A spec is `Done` only when **all** of these hold:

- [ ] Backend follows the Service–Repository rules (`AGENTS.md` §7).
- [ ] Frontend follows the Inertia/React rules (`AGENTS.md` §8).
- [ ] Every user-visible string is translatable and added to **all five**
      `lang/app/*.json` dictionaries (`AGENTS.md` §8.11).
- [ ] Every fixed value set is an enum — no magic strings.
- [ ] Audit coverage declared where the change is security/business relevant
      (skill `add-audit-logging`).
- [ ] Unit test (service) **and** feature test (controller/page) exist.
- [ ] `composer check` passes.
- [ ] `ROADMAP.md` row updated to `Done`.

## 6. How to work a spec

1. Read `AGENTS.md`, the relevant `.agents/rules/*`, and the spec.
2. Use the matching skill (`generate-module`, `create-inertia-feature`,
   `generate-tests`, `add-translation`, `add-permission`, `add-audit-logging`,
   `clockify-sync-feature`, `add-export`).
3. Implement backend first (model → enum → DTO → repository → service →
   request/resource → controller → route → tests), then the UI.
4. Run `composer check`; fix everything.
5. Update the spec status and the `ROADMAP.md` row.

## 7. The Pipeline Experience

The flagship UI concern of the product is how long-running work is shown: a
**live, honest, retryable timeline** for historical imports, incremental syncs
and exports. That is why this first batch of specs is the `pipeline/` epic plus
the `foundations/` primitives it needs. The goal is a run view that is clearly
better than anything Clockify offers: real stage-by-stage progress, throughput,
ETA, an append-only event timeline, visible retries/backoff, resumable
checkpoints, and reassurance that the user can leave the page.

The existing Data Processing Center (`/activity`, `DataProcessingJob`,
`ProcessImport`/`ProcessExport`, `use-job-poll`) is the starting point; these
specs evolve it into the shared pipeline surface that Clockify sync will also
use.
