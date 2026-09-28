# Evoriq Roadmap — Spec Backlog

The full product backlog as **bite-sized specs**. Specs with a folder + file
exist and are detailed; specs marked `stub` are planned and will be written
when their phase starts. Status mirrors the spec header.

Legend: **S** ≤ 0.5 day · **M** ≤ 1.5 days · **L** ≤ 3 days.
Status: `Draft` `Ready` `In Progress` `Blocked` `Done` `stub`.

> Ordering inside a phase is the recommended build order. Cross-phase
> dependencies are listed per spec.

## Build order across phases

The phase tables list the recommended order **within** each phase. One Phase 0
foundation is pulled forward **just-in-time** against Phase 1 (marked `+` in the
`Depends` column); `FND-09` (frontend test runner) is already **Done** and
unblocks the pipeline logic tests. The rest of Phase 0 is not on the Phase 1
critical path.

| When | Spec | Why |
| ---- | ---- | --- |
| Immediately before `PIPE-04` | `FND-06` — Loading / empty / error state system | The timeline primitive and every run view reuse these state primitives, so build them before the rail. |

`FND-05`, `FND-07`, `FND-08` and `FND-10` are **not** on the Phase 1 path; start
them when their consuming phases begin (`FND-05`/`FND-07` with DASH/REP, and the
`FND-10` accessibility audit after `FND-01..06`).

---

## Architecture (do first)

Foundational decisions that everything else builds on. See `DECISIONS.md` and
the verified API contract in `reference/clockify-api.md`.

| ID       | Spec                                                                 | Est | Status | Depends | Scope |
| -------- | -------------------------------------------------------------------- | --- | ------ | ------- | ----- |
| `ORG-01` | [Organization-ready schema](architecture/ORG-01-organization-ready-schema.md) | M | Done | — | `organizations` table + `organization_id` on all Clockify/pipeline/analytics tables; single default org now, multi-org later. Blocks all data-model specs. |

## Phase 0 — Craft & UI foundations

Reusable primitives that every later phase depends on. Build the hardest,
most-reused UI pieces first.

| ID       | Spec                                                       | Est | Status | Depends | Scope |
| -------- | ---------------------------------------------------------- | --- | ------ | ------- | ----- |
| `FND-01` | [Motion & feedback system](foundations/FND-01-motion-and-feedback.md) | M | Done | — | Motion tokens, reduced-motion, transitions, skeletons, optimistic + toast conventions. |
| `FND-02` | [Formatting utilities](foundations/FND-02-formatting-utilities.md) | S | Done | — | `lib/format.ts`: duration/hours/percent/currency/bytes/compact-number/relative-time, locale-aware. |
| `FND-03` | [Date-range & period picker](foundations/FND-03-date-range-picker.md) | M | Done | FND-02 | Preset periods + custom range + comparison period; URL/Inertia-synced. |
| `FND-04` | [Charting foundation](foundations/FND-04-charting-foundation.md) | L | Done | FND-02 | Pick + wrap a chart lib; themable series, axis, tooltip, legend, empty/loading. |
| `FND-05` | Data-viz primitives (MetricCard v2, delta, sparkline) | M | stub | FND-01..04 | Trend-aware metric cards, delta chips, inline sparklines. |
| `FND-06` | Loading / empty / error state system                  | S | stub | FND-01 | Shared `<Skeleton>`, `<EmptyState>`, `<ErrorState>`, `<InlineAlert>` patterns. **Scheduled just-in-time before PIPE-04.** |
| `FND-07` | Table & list v2 (density, sticky, saved views)        | M | stub | FND-01 | Density toggle, sticky headers, column persistence, saved filter views. |
| `FND-08` | Command palette & navigation polish                   | M | stub | — | ⌘K palette over routes/actions; recent items. |
| `FND-09` | [Frontend test runner (`vp test` + Vitest)](foundations/FND-09-frontend-test-runner.md) | M | Done | — | Vitest + Testing Library wired into `composer check`; RTL helpers. |
| `FND-10` | Accessibility & high-contrast audit                   | M | stub | FND-01..06 | Keyboard/focus/contrast pass across all surfaces. |

## Phase 1 — The Pipeline Experience (flagship)

The live timeline for imports, syncs and exports. Detailed in `pipeline/`.

| ID        | Spec                                                          | Est | Status | Depends         | Scope |
| --------- | ------------------------------------------------------------- | --- | ------ | --------------- | ----- |
| `PIPE-01` | [Pipeline event stream](pipeline/PIPE-01-event-stream.md)     | L | Done | —               | Append-only `pipeline_events` + recorder; retrofit `DataProcessingJob`. |
| `PIPE-02` | [Run aggregation & progress contract](pipeline/PIPE-02-run-aggregation.md) | L | Done | PIPE-01 | Stage/ETA/throughput/failure taxonomy; `PipelineRunResource`. |
| `PIPE-03` | [Live polling transport](pipeline/PIPE-03-live-polling.md)   | M | Draft | PIPE-02 | `useLivePoll`: visibility-aware, adaptive, backoff, shared registry. |
| `PIPE-04` | [Timeline UI primitive](pipeline/PIPE-04-timeline-primitive.md) | M | Draft | FND-01 (+ FND-06) | Reusable vertical timeline (rail, markers, time gutter, groups, a11y). |
| `PIPE-05` | [Run timeline UI](pipeline/PIPE-05-run-timeline-ui.md)        | L | Draft | PIPE-03, PIPE-04 | Flagship run view: progress hero, stage lanes, streaming event timeline. |
| `PIPE-06` | [Run detail & event inspector](pipeline/PIPE-06-run-detail-inspector.md) | M | Draft | PIPE-05 | Tabs: overview/timeline/errors/artifacts/params/raw; error grouping. |
| `PIPE-07` | [Retry, resume, cancel & backoff UX](pipeline/PIPE-07-retry-resume-cancel.md) | M | Draft | PIPE-05         | Cooperative cancel, retry run/stage, resume, backoff display, failed view. |
| `PIPE-08` | [Global pipeline indicator](pipeline/PIPE-08-global-pipeline-indicator.md) | M | Draft | PIPE-03         | Nav/sidebar live status pill + popover with active runs. |
| `PIPE-09` | [Historical import progress experience](pipeline/PIPE-09-import-progress-experience.md) | L | Draft | PIPE-05, FND-03 | Range → plan preview → live import → completion, "you can leave". |
| `PIPE-10` | [Sync health & observability](pipeline/PIPE-10-sync-health-observability.md) | M | Draft | PIPE-02         | Admin page: success rate, durations, queue depth, API usage, correlation search. |
| `PIPE-11` | [Pipeline lifecycle notifications](pipeline/PIPE-11-pipeline-notifications.md) | S | Draft | PIPE-02         | Queued/started/completed/failed/retrying notifications + preferences. |
| `PIPE-12` | [Pipeline i18n, states & retention](pipeline/PIPE-12-i18n-states-retention.md) | M | Draft | PIPE-01..11     | 5-locale strings, empty/error states, event coalescing + pruning. |

## Phase 2 — Clockify connection & workspace

| ID        | Spec                                                     | Est | Status | Depends | Scope |
| --------- | -------------------------------------------------------- | --- | ------ | ------- | ----- |
| `CONN-01` | [Connection model & credentials](connection/CONN-01-connection-model.md) | M | Draft | ORG-01 | `clockify_connections`; encrypted credentials, region/subdomain base URLs, plan/limit profile. |
| `CONN-02` | [Validation & capability detection](connection/CONN-02-connection-validation.md) | M | Draft | CONN-01 | Verify key, detect workspace/plan/limits/region; error mapping. |
| `CONN-03` | [Workspace discovery & selection](connection/CONN-03-workspace-selection.md) | S | Draft | CONN-02 | `clockify_workspaces` + active-workspace selection. |
| `CONN-04` | [Connect flow UI](connection/CONN-04-connect-flow-ui.md) | M | Draft | CONN-02, CONN-03, CONN-07 | Key entry, verify, workspace picker, plan/budget display. |
| `CONN-05` | [Manage connections](connection/CONN-05-manage-connections.md) | M | Draft | CONN-02 | Rename/reverify/rotate/disable/disconnect (+ purge choice). |
| `CONN-06` | [Policy, permissions & audit](connection/CONN-06-policy-permissions-audit.md) | S | Draft | CONN-01 | `connection.view`/`connection.manage`, policy, audit, redaction. |
| `CONN-07` | [API error taxonomy & messaging](connection/CONN-07-api-error-taxonomy.md) | S | Draft | CONN-02 | `ApiErrorCode` + central mapper → friendly label/hint/action. |
| `CONN-08` | [Connection status & security](connection/CONN-08-connection-status-security.md) | S | Draft | CONN-01, CONN-02 | Status resource/widget + secret-leak verification. |
| `CONN-09` | [Sync & connection settings](connection/CONN-09-sync-connection-settings.md) | M | Draft | CONN-01, SYNC-11 | Auto-sync schedule, reconciliation, tz/currency, rate overrides. |

## Phase 3 — Sync infrastructure

| ID        | Spec                                                     | Est | Status | Depends | Scope |
| --------- | -------------------------------------------------------- | --- | ------ | ------- | ----- |
| `SYNC-01` | [Sync state schema](sync/SYNC-01-sync-state-schema.md) | L | Draft | ORG-01, CONN-01 | Runs, jobs, checkpoints, api usage, entity changes, deleted entities, raw records. |
| `SYNC-02` | [Plan-aware API usage accounting](sync/SYNC-02-api-usage-accounting.md) | M | Draft | SYNC-01, CONN-02 | Self-accounted budget (Free 30/h, paid 50/s) shared by every request path. |
| `SYNC-03` | [Sync planner](sync/SYNC-03-sync-planner.md) | L | Draft | SYNC-01, SYNC-02, CONN-03 | Range/scope → ordered budget-aware plan with 31-day partitions. |
| `SYNC-04` | [Resumable sync job engine](sync/SYNC-04-resumable-sync-job.md) | L | Draft | SYNC-01, SYNC-02, SYNC-03 | Generic checkpointed per-page job; crash-resume. |
| `SYNC-05` | [Raw record store](sync/SYNC-05-raw-record-store.md) | M | Draft | SYNC-01 | Persist/dedupe upstream payloads for recovery. |
| `SYNC-06` | [Entity Changes adapter](sync/SYNC-06-entity-changes-adapter.md) | M | Draft | SYNC-01 | Isolated created/updated/deleted feed with documented caveats. |
| `SYNC-07` | [Deleted entities application](sync/SYNC-07-deleted-entities-application.md) | M | Draft | SYNC-06 | Apply deletions idempotently; preserve historical facts. |
| `SYNC-08` | [Idempotent upsert conventions](sync/SYNC-08-idempotent-upsert.md) | M | Draft | SYNC-01 | Natural key + upsert contract + create/update counters. |
| `SYNC-09` | [Sync run orchestration](sync/SYNC-09-sync-run-orchestration.md) | L | Draft | SYNC-03, SYNC-04 | Plan → run → budget-aware dispatch → finalize → analytics. |
| `SYNC-10` | [Rolling reconciliation](sync/SYNC-10-rolling-reconciliation.md) | M | Draft | SYNC-09, SYNC-06 | Daily 7-day / weekly 30–31-day re-fetch. |
| `SYNC-11` | [Daily automatic sync](sync/SYNC-11-daily-automatic-sync.md) | M | Draft | SYNC-09, SYNC-06 | Scheduled incremental sync + checkpoint freshness. |
| `SYNC-12` | [Manual "Sync Now"](sync/SYNC-12-manual-sync-now.md) | M | Draft | SYNC-09 | Incremental, deduped, budget-guarded manual trigger. |
| `SYNC-13` | [Failure/retry/resume policy](sync/SYNC-13-failure-retry-resume-policy.md) | M | Draft | SYNC-04, SYNC-09 | Backoff, attempt caps, resume, stale reaper. |
| `SYNC-14` | [Pipeline event integration](sync/SYNC-14-pipeline-event-integration.md) | S | Draft | SYNC-04, PIPE-01 | Emit sync lifecycle into the shared PIPE stream. |
| `SYNC-15` | [Webhook endpoint](sync/SYNC-15-webhook-endpoint.md) | M | Draft | CONN-01 | Token-validated, fast-ack receiver. |
| `SYNC-16` | [Webhook incremental fetch](sync/SYNC-16-webhook-incremental-fetch.md) | M | Draft | SYNC-15, SYNC-06 | Event → targeted rate-limited fetch/delete. |
| `SYNC-17` | [API usage & budget UI](sync/SYNC-17-api-usage-ui.md) | M | Draft | SYNC-02, PIPE-08 | Navbar indicator + popover; honest hourly/per-second wording. |
| `SYNC-18` | [Sync status & freshness contract](sync/SYNC-18-sync-status-freshness.md) | M | Draft | SYNC-09, SYNC-10, SYNC-11 | Last synced, data through, historical range, next run, health. |
| `SYNC-19` | [Webhook registration & lifecycle](sync/SYNC-19-webhook-registration-lifecycle.md) | M | Draft | SYNC-15, CONN-01 | Register/rotate/disable + delivery health. |
| `SYNC-20` | [Priority, scheduling & backpressure](sync/SYNC-20-priority-scheduling-backpressure.md) | M | Draft | SYNC-02, SYNC-09 | Priority→channel mapping, budget gate, concurrency caps. |

## Phase 4 — MVP entity sync

| ID       | Spec                                                    | Est | Status | Depends | Scope |
| -------- | ------------------------------------------------------- | --- | ------ | ------- | ----- |
| `ENT-00` | [Ingestion framework](entities/ENT-00-ingestion-framework.md) | L | Draft | ORG-01, SYNC-04/05/08 | The per-entity `SyncHandler` contract, registry and mapping conventions. |
| `ENT-01` | [Workspace dimension](entities/ENT-01-workspace-dimension.md) | M | Draft | ORG-01, ENT-00, CONN-03 | Enrich `clockify_workspaces` (currency, tz, rates, org id). |
| `ENT-02` | [Users & memberships](entities/ENT-02-users-memberships.md) | L | Draft | ORG-01, ENT-00 | Identity + capacity + historical rates. |
| `ENT-03` | [Clients](entities/ENT-03-clients.md) | M | Draft | ORG-01, ENT-00 | Client dimension. |
| `ENT-04` | [Projects & members](entities/ENT-04-projects-members.md) | L | Draft | ORG-01, ENT-00, ENT-02, ENT-03 | Primary dimension + per-user project rates. |
| `ENT-05` | [Tasks](entities/ENT-05-tasks.md) | M | Draft | ORG-01, ENT-00, ENT-04 | Nested per-project tasks. |
| `ENT-06` | [Tags & entry tags](entities/ENT-06-tags.md) | M | Draft | ORG-01, ENT-00, ENT-07 | Relational tags (no JSON). |
| `ENT-07` | [Time entries](entities/ENT-07-time-entries.md) | L | Draft | ORG-01, ENT-00, ENT-02/04/05/06 | Primary fact; **per-user** fetch; derive duration. |
| `ENT-08` | [Time entry rates](entities/ENT-08-time-entry-rates.md) | M | Draft | ORG-01, ENT-00, ENT-07 | Historical rates per entry. |
| `ENT-09` | [Custom fields](entities/ENT-09-custom-fields.md) | M | Draft | ORG-01, ENT-00 | Field definitions + option sets. |
| `ENT-10` | [Time entry CF values](entities/ENT-10-time-entry-custom-field-values.md) | M | Draft | ORG-01, ENT-00, ENT-07, ENT-09 | Entry metadata facts. |
| `ENT-11` | [User CF values](entities/ENT-11-user-custom-field-values.md) | S | Draft | ORG-01, ENT-00, ENT-02, ENT-09 | User/team metadata. |
| `ENT-12` | [User groups & members](entities/ENT-12-user-groups.md) | M | Draft | ORG-01, ENT-00, ENT-02 | Team dimension. |
| `ENT-13` | [Deletions & restores](entities/ENT-13-deletions-restores.md) | M | Draft | SYNC-07, ENT-01..12 | Per-entity delete/restore policy + cascades. |
| `ENT-14` | [Historical import wizard end-to-end](entities/ENT-14-historical-import-wizard.md) | L | Draft | SYNC-03/09, PIPE-09, ENT-01..13 | Real import orchestration + concurrency guard. |
| `ENT-15` | [ID mapping & resolution](entities/ENT-15-id-mapping-resolution.md) | M | Draft | ORG-01, ENT-00 | Batched Clockify↔internal id resolution. |

## Phase 5 — Analytics engine

| ID       | Est | Status | Depends | Scope |
| -------- | --- | ------ | ------- | ----- |
| `ANA-01` | L | stub | ENT-07 | Derived metric tables (daily/monthly × user/project/client). |
| `ANA-02` | L | stub | ANA-01 | Aggregation service + repository (query-only, no Clockify). |
| `ANA-03` | M | stub | ANA-02, SYNC-09 | Aggregation jobs run after sync; incremental recompute. |
| `ANA-04` | M | stub | ANA-02 | Backfill/recompute command. |
| `ANA-05` | M | stub | ANA-01 | KPI catalog + metric definitions (single source of truth). |
| `ANA-06` | L | stub | ANA-02 | Comparison engine (period vs period, YTD, overlay). |
| `ANA-07` | M | stub | ANA-01 | Timezone & day/week/month boundary handling. |
| `ANA-08` | M | stub | ANA-01 | Billable/cost/rate attribution rules. |
| `ANA-09` | L | stub | ANA-02, P2-01, P2-02 | Utilization & capacity (expected vs tracked hours; required by TDR §29 comparisons). |
| `ANA-10` | L | stub | ANA-02, ANA-08 | Cost, revenue & project profitability. |
| `ANA-11` | M | stub | ANA-08 | Rate analysis (effective rates, rate drift over time). |
| `ANA-12` | L | stub | ANA-02 | Forecasting & advanced trend detection (post-MVP). |

## Phase 6 — Dashboard & reports

| ID        | Est | Status | Depends | Scope |
| --------- | --- | ------ | ------- | ----- |
| `DASH-01` | M | stub | FND-05, ANA-05 | Dashboard hero: freshness, KPI cards. |
| `DASH-02` | M | stub | FND-04 | Hours-over-time chart. |
| `DASH-03` | M | stub | FND-04 | Distribution charts (project/user/client). |
| `DASH-04` | S | stub | FND-04 | Billable vs non-billable. |
| `DASH-05` | M | stub | PIPE-08, DASH-01 | Data-freshness / sync-status hero. |
| `REP-01` | L | stub | FND-03, ANA-02 | Report builder shell (period + grouping + filters). |
| `REP-02` | M | stub | REP-01 | User report. |
| `REP-03` | M | stub | REP-01 | Project report. |
| `REP-04` | M | stub | REP-01 | Client report. |
| `REP-05` | M | stub | REP-01 | Task & tag reports. |
| `REP-06` | L | stub | REP-01 | Multi-dimension grouping + drill-down. |
| `REP-07` | L | stub | ANA-06, REP-01 | Historical comparison view. |
| `REP-08` | M | stub | REP-02 | Report table + export entry points. |
| `REP-09` | M | stub | REP-01 | Saved views / bookmarks. |
| `REP-10` | M | stub | REP-01 | Report templates (reusable report definitions). |
| `REP-11` | L | stub | DASH-01 | Custom dashboards (user-composable widgets; post-MVP). |

## Phase 7 — Exports & delivery

| ID       | Est | Status | Depends | Scope |
| -------- | --- | ------ | ------- | ----- |
| `EXP-01` | L | stub | ANA-02 | PDF report template engine (proper report, not screenshot). |
| `EXP-02` | M | stub | EXP-01, REP-08 | Register report entities on the Data Processing Center. |
| `EXP-03` | M | stub | EXP-02 | Scheduled & emailed reports (post-MVP). |

## Phase 8 — Observability & ops

| ID       | Est | Status | Depends | Scope |
| -------- | --- | ------ | ------- | ----- |
| `OPS-01` | M | stub | SYNC-09 | Sync metrics into Pulse (runs, errors, rate-limit events). |
| `OPS-02` | M | stub | SYNC-09, ENT-07 | Repair/backfill tools (rebuild from raw records). |
| `OPS-03` | M | stub | SYNC-01 | Retention/pruning (raw records, entity changes, events). |
| `OPS-04` | M | stub | CONN-01 | Runbooks & alert thresholds (stale sync, budget low, failures). |
| `OPS-05` | M | stub | CONN-01 | Data retention & deletion policy: disconnect/erase synced data, account data export, retention windows. |

## Phase 9 — Post-MVP entities

| ID      | Est | Status | Depends | Scope |
| ------- | --- | ------ | ------- | ----- |
| `P2-01` | L | stub | SYNC-08 | Scheduled assignments (planned vs actual). |
| `P2-02` | M | stub | SYNC-08 | Holidays. |
| `P2-03` | L | stub | SYNC-08 | PTO policies, time-off requests, balances. |
| `P2-04` | M | stub | SYNC-08 | Approval requests. |
| `P2-05` | L | stub | SYNC-08 | Invoices + invoice items + expenses. |

## Cross-cutting / security

| ID       | Est | Status | Depends | Scope |
| -------- | --- | ------ | ------- | ----- |
| `SEC-01` | M | stub | CONN-01 | Credential encryption, key rotation, secret redaction checks. |
| `SEC-02` | M | stub | CONN-01, ENT-* | Policies on every synced dimension/fact; ownership from `auth()->id()`. |
| `I18N-01`| — | ongoing | each spec | Every new string added to all five `lang/app/*.json`. |

## Phase 10 — Future integrations (post-MVP)

Explicitly out of MVP per TDR §44; kept here so the architecture leaves room.

| ID       | Est | Status | Depends | Scope |
| -------- | --- | ------ | ------- | ----- |
| `INT-01` | L | stub | ENT-* | Time-source abstraction + a CSV/other-provider importer so non-Clockify sources can feed the same analytics model. |

## Decisions & open spikes

Resolved decisions live in [`DECISIONS.md`](DECISIONS.md). Summary:

| Spike      | Resolution                                                                                          | Ref |
| ---------- | --------------------------------------------------------------------------------------------------- | --- |
| `SPIKE-01` | Resolved — ingest underlying REST entities, **not** the Reports API.                                | DEC-002 |
| `SPIKE-02` | Resolved — JS scripts are examples; per-user PDF reports are **ours**.                              | DEC-007 |
| `SPIKE-03` | Resolved — local disk now; external object storage deferred.                                        | DEC-004 |
| `SPIKE-04` | Resolved — self-hosted, not SaaS; no billing.                                                       | DEC-001 |
| `SPIKE-05` | Resolved — plan-aware budgeting (Free 30 req/h).                                                    | DEC-006 |
| `SPIKE-A`  | Resolved — Free keeps full history (≥60 months); 31-day cap is per-report only.                     | DEC-009 |

Genuinely open:

| ID         | Question                                                                                                       | Blocks            |
| ---------- | -------------------------------------------------------------------------------------------------------------- | ----------------- |
| `SPIKE-06` | Analytics cache/invalidation strategy after incremental sync (how much is recomputed per sync vs on demand).    | ANA-03            |
