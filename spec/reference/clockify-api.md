# Reference — Clockify API (verified)

- **Status:** Living document
- **Sources:** https://docs.clockify.me/ (full REST/reports/audit/experimental reference),
  Clockify Help (plan limits), CAKE.com blog (2026-04 free-plan changes).
- **Verified:** 2026-09-27
- **Purpose:** the single factual reference the specs build on. The root
  `clockify_free_probe.js` / `clockify_monthly_reports.js` are **examples only**,
  not the contract.

> Re-check this file whenever a Clockify breaking change is announced. The
> experimental Entity Changes API is explicitly subject to change and must stay
> behind an adapter (`SYNC-06`).

## 1. Authentication

- Header `X-Api-Key: <key>` **or** `X-Addon-Token: <token>`.
- API keys are generated per user in Profile Settings.
- **Subdomain workspaces need a workspace-specific API key**; the generic key
  will not work.
- All requests are server-side; keys are encrypted at rest, never sent to the
  browser (`SEC-01`).

## 2. Base URLs

| Context                  | Regular API                        | Reports API                                   |
| ------------------------ | ---------------------------------- | --------------------------------------------- |
| Global                   | `https://api.clockify.me/api/v1`   | `https://reports.api.clockify.me/v1`          |
| Regional (non-subdomain) | `https://euc1.clockify.me/api/v1`  | `https://use2.clockify.me/report/v1` (region) |
| Regional (subdomain)     | `https://euc1.clockify.me/api/v1`  | `https://<subdomain>.clockify.me/report/v1`   |
| Developer                | `https://developer.clockify.me/api/v1` | `https://developer.clockify.me/report/v1` |

Regional prefixes: **EU `euc1`, USA `use2`, UK `euw2`, AU `apse2`.**
The base URL is therefore **per connection/workspace configurable**, not a
constant (`CONN-01`, `config/clockify.php`).

## 3. Rate limits (critical)

| Auth / plan                                        | Limit                                    |
| -------------------------------------------------- | ---------------------------------------- |
| `X-Addon-Token` (addon) on one workspace            | **50 requests/second**                   |
| `X-Api-Key`, paid plan                              | 50 requests/second (standard)            |
| `X-Api-Key`, **Free plan** (newly created workspaces) | **30 requests/hour per workspace**     |

- Error shape: HTTP `429` / `"Too many requests"`.
- **No `X-RateLimit-*` response headers are documented.** Usage must be
  self-accounted in `clockify_api_usage` (`SYNC-02`).
- The 50 req/s figure is *per addon on one workspace*; API-key and endpoint
  policies can differ, so the limiter must be **configurable per connection**
  and **plan-aware** (`CONN-02` detects the plan; `SYNC-20` enforces budget).
- Free-plan consequence: **a 5-year import is inherently a multi-hour job**
  (30 req/h). This is why progress, resumability and scheduling are core.

## 4. Pagination

- Base list endpoints: query `page` (1-indexed, default 1) and `page-size`
  (default 50).
- Response header **`Last-Page`** = `true` means final page.
- **Naming is inconsistent across the API** — do not assume:
  - base lists → `page` + `page-size` (hyphen);
  - `POST .../users/info` filter → `pageSize` (camel);
  - Reports `detailedFilter` → `page` + `pageSize` (camel);
  - Entity Changes → `page` (default `"0"`) + `limit` (default `"50"`);
  - Webhook logs/statuses → `page` + `size`.
- Max page size: Clockify help states **5000 for base**, **1000 for report**
  endpoints; `time-entries/status/in-progress` documents `1..1000`. The OpenAPI
  dump does not state base max. Treat as advisory and clamp conservatively
  (`config/clockify.php` `pagination.max_page_size`).

## 5. Entity endpoints (what we ingest)

Primary list endpoints (all paged unless noted):

| Our entity                  | Path (prefix `/v1/workspaces/{workspaceId}`) | Notes |
| --------------------------- | -------------------------------------------- | ----- |
| Workspace                   | `GET /workspaces` · `GET /workspaces/{id}`   | includes `cakeOrganizationId`, `featureSubscriptionType`, `features[]`, `currencies`, `workspaceSettings`, `subdomain` |
| Users                       | `GET /users`                                 | `memberships=NONE\|ALL\|WORKSPACE\|PROJECT\|USERGROUP`, `include-roles`; rate fields only via profile/rate endpoints |
| User groups                 | `GET /user-groups`                           | `teamManagers[]`, `userIds[]` |
| Clients                     | `GET /clients`                               | `currencyCode`, `archived`, `email`, `address` |
| Projects                    | `GET /projects`                              | `clientId`, `billable`, `hourlyRate`/`costRate` (`{amount, since}`), `memberships`, `archived`, `public` |
| Project memberships         | `PATCH/POST /projects/{id}/memberships`      | `UserIdWithRatesRequest` (shape not fully documented) |
| Tasks                       | `GET /projects/{projectId}/tasks`            | per-project; `assigneeId(s)`, `status`, `duration` (ISO-8601) |
| Tags                        | `GET /tags`                                  | `archived` |
| Custom fields               | `GET /custom-fields`                         | `entityType` `TIMEENTRY\|USER`, `type`, `status`, `allowedValues` |
| Time entries                | `GET /user/{userId}/time-entries`            | **per-user**; `start`,`end`,`hydrated`,`page`,`page-size` |
| Time entries (by id)        | `POST /time-entries/batch`                   | body `timeEntryIds[]`, `hydrated`; no date paging |
| Time entry rates            | via `hydrated=true` on entries + `TIME_ENTRY_RATE` changes | `hourlyRate`,`costRate` |
| In progress entries         | `GET /time-entries/status/in-progress`       | response schema not documented |

**Time entry response fields (documented):** `id`, `userId`, `workspaceId`,
`projectId`, `taskId`, `tagIds[]`, `billable`, `isLocked`, `kioskId`,
`description`, `timeInterval {start,end}`, `type` (`REGULAR|BREAK`), and when
`hydrated=true` also `hourlyRate`/`costRate` and `customFieldValues`.

**Gaps / risks:**
- There is **no documented `duration` field** on a time entry → derive from
  `timeInterval.start/end`.
- `timeInterval` is `null` in every official sample (untracked/running cases) →
  handle nulls.
- `costRate`/`hourlyRate` shapes are shown as `null` in samples; the
  `{amount, since}` shape comes from the write bodies.
- `TIME_ENTRY_RATE` is an Entity Changes type but no dedicated list endpoint is
  documented → rely on `hydrated` + entity changes.
- Membership/custom-field filter schemas and the full `features` /
  `featureSubscriptionType` enums are **not expanded** in the reference.

## 6. Entity Changes (Experimental)

- `GET /v1/workspaces/{workspaceId}/entities/created`
- `GET /v1/workspaces/{workspaceId}/entities/updated`
- `GET /v1/workspaces/{workspaceId}/entities/deleted`
- Params: `type` (**required**, multi-value), `start`, `end`, `page` (default
  `"0"`), `limit` (default `"50"`).
- `type` values: `CLIENTS`, `PROJECTS`, `TAGS`, `TASKS`,
  `SCHEDULED_ASSIGNMENT`, `TIME_ENTRY`, `TIME_ENTRY_RATE`,
  `TIME_ENTRY_CUSTOM_FIELD_VALUE`, `CUSTOM_FIELDS`, `USER`, `USER_GROUPS`,
  `INVOICES`, `APPROVAL_REQUESTS`, `BALANCE`, `HOLIDAYS`, `PTO_POLICY`,
  `TIME_OFF_REQUEST`.
- Date range: if `start` omitted it defaults to `end − 30 days` (and vice
  versa); both omitted → relative to now.
- **Caveats:** `/updated` **excludes** entities both created and updated in the
  range (use `/created` for those). `/deleted` reflects ~1 minute after
  deletion and **omits** entities created and deleted within the same range.
  `deleted` response shapes differ between the schema and the guide
  (`response[]` vs bare array) — tolerate both.
- Documented stable use case covers only `TIME_ENTRY`, `TIME_ENTRY_RATE`,
  `TIME_ENTRY_CUSTOM_FIELD_VALUE`; treat other types as best-effort.

## 7. Reports API (explicitly NOT our ingestion source)

- `POST /v1/workspaces/{workspaceId}/reports/detailed` (also `/summary`,
  `/weekly`, `/reports/attendance`, `/reports/expenses/detailed`).
- Body: `dateRangeStart`, `dateRangeEnd`, `dateRangeType`, `exportType`
  (`JSON|JSON_V1|PDF|CSV|XLSX|ZIP`), `timeZone`, `detailedFilter`
  (`page`, `pageSize`, …), plus `users`/`projects`/`tags`/`clients` filters.
- **Free plan caps report intervals at 31 days.** CSV/XLSX export is paid-only.
- The `/reports/detailed` **response schema is not documented** in the reference
  dump; only `timeentries[].{_id,description,timeInterval,customFields}` appears
  in a guide.
- **Decision:** we do not ingest reports. We ingest entities (TDR §9.1) and
  build our own reports (`DEC-002`). The Reports API may later be used only as a
  convenience/verification, never as the source of truth.

## 8. Webhooks

- Path `.../v1/workspaces/{workspaceId}/webhooks` — `GET`, `POST` create, `PUT`
  update, `DELETE`, `PATCH .../token` (rotate, invalidates old), `POST
  .../logs`, `GET .../statuses`.
- Create/update body: `name` (2..30), `url`, `triggerSource[]`,
  `triggerSourceType` (`PROJECT_ID|USER_ID|TAG_ID|TASK_ID|WORKSPACE_ID|ASSIGNMENT_ID|EXPENSE_ID`),
  `webhookEvent` (**56 values**, e.g. `NEW_TIME_ENTRY`, `TIME_ENTRY_UPDATED`,
  `TIME_ENTRY_DELETED`, `TIME_ENTRY_RESTORED`, `TIME_ENTRY_SPLIT`, `NEW_PROJECT`,
  `PROJECT_UPDATED`, `PROJECT_DELETED`, `NEW_TASK`, `NEW_CLIENT`, `NEW_TAG`,
  `USER_UPDATED`, `TIME_OFF_REQUEST_*`, `EXPENSE_*`, `ASSIGNMENT_*`, …).
- Returned object includes `authToken` used as the webhook auth header.
- **Plan limits:** FREE 3 webhooks; BASIC/STANDARD/PRO 10/user up to 100/workspace;
  ENTERPRISE 100/user up to 300/workspace.
- Logs/statuses support `SUCCEEDED|RETRYING|FAILED` and `retryCount` →
  observability for `SYNC-19`.

## 9. Workspace ↔ organization

- Every workspace response carries **`cakeOrganizationId`** plus
  `featureSubscriptionType` and `features[]`.
- This is the natural external key for our **organization-ready schema**
  (`ORG-01`): our internal `organizations.clockify_organization_id` maps to
  Clockify's cake organization; multiple Clockify workspaces can share one org.
- `POST /workspaces` accepts `organizationId`, confirming the org↔workspace
  relationship.

## 10. Probe findings (verified on a Free workspace, 2026-09-27)

`clockify_free_probe.js` output on a Free workspace:

- `/reports/detailed`: **31-day window → HTTP 200; 32-day → HTTP 400.**
- Historical 31-day windows at **1, 3, 6, 12, 18, 24, 36, 48 and 60 months back
  are all accessible (HTTP 200)**.

Interpretation:

- Free workspaces keep **full historical depth** (≥ 60 months). There is **no
  1-year/31-day history cut-off** — only a per-**report-interval** cap.
- The probe used the **Reports API**, not the base REST entity endpoints we
  ingest from (`DEC-002`). It therefore does **not** prove the base
  `GET /user/{id}/time-entries` endpoint accepts wide ranges. We proceed on the
  assumption that base REST is not interval-capped (the cap is documented only
  for reports), while defaulting to **31-day partitions** (`SYNC-03`) so the
  pipeline behaves correctly either way.
- `SPIKE-A` resolved (see `DEC-009`).

## 11. What we still don't know (resolve during implementation)

- Exact response schema for `/reports/detailed` (we avoid it, so low impact).
- Full person/membership/custom-field filter schemas.
- Max `page-size` for base endpoints (help says 5000; clamp anyway).
- Whether `X-Api-Key` per-second limits differ from the addon 50/s figure.
- Subdomain/regional base-URL detection strategy for auto-configured connections.
