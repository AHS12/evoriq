# Technical Design — Clockify Analytics Platform

**Status:** Draft
**Version:** 0.3
**Product Type:** Lightweight SaaS / Analytics Platform
**Primary Stack:** Laravel + React + PostgreSQL
**Data Source:** Clockify API

---

# 1. Product Overview

The product is a lightweight analytics and reporting platform built on top of Clockify.

It does **not** attempt to replace Clockify's time-tracking applications.

Users continue using:

* Clockify Web
* Clockify Android
* Clockify iOS

for everyday time tracking.

Our application connects to the user's Clockify workspace, synchronizes selected historical data into PostgreSQL, and provides a dedicated experience for:

* Historical analytics
* Reporting
* Charts and dashboards
* Cross-period comparisons
* Custom date ranges
* Data exports
* PDF reports
* Long-term historical analysis

The fundamental product model is:

> **Clockify is the time-tracking system. Our application is the historical analytics system.**

---

# 2. Problem

Clockify provides time tracking and reporting, but its reporting experience and date-range limitations can make long-term analysis inconvenient.

The proposed application solves this by maintaining an independent synchronized dataset.

Instead of repeatedly querying Clockify for every report:

```text
User
  ↓
Our Dashboard
  ↓
Clockify API
  ↓
Report
```

we use:

```text
Clockify
   ↓
Synchronization Engine
   ↓
PostgreSQL
   ↓
Analytics
   ↓
Dashboard
```

Once data has been synchronized, historical reports are generated from our own database.

---

# 3. Product Principles

The product should follow four principles.

## 3.1 Simple

The user should not need to understand synchronization technology.

## 3.2 Analytics-first

The application should focus on understanding time data rather than reproducing Clockify's tracking interface.

## 3.3 API-friendly

Synchronization must deliberately respect Clockify's API constraints.

## 3.4 Historical

Once imported, data should remain available for long-term analysis within our system.

---

# 4. Goals

## Primary Goals

* Import historical Clockify data.
* Maintain synchronized local data.
* Provide long-term reporting.
* Provide flexible date ranges.
* Provide useful visual analytics.
* Allow users to manually trigger synchronization.
* Automatically synchronize data on a schedule.
* Make API consumption transparent.
* Never unnecessarily overload or rate-limit Clockify.
* Provide fast reports from PostgreSQL.

---

# 5. Non-Goals

The initial product will not replace Clockify's tracking functionality.

Out of scope for MVP:

* Native time tracking
* Start/stop timer
* Mobile applications
* Project management
* Task management
* Employee scheduling
* Team chat
* Full Clockify UI replication

Clockify remains the source application for time tracking.

---

# 6. Technology Stack

## Backend

**Laravel**

Responsibilities:

* Authentication
* Authorization
* Clockify API integration
* Synchronization
* Queue processing
* Scheduling
* Analytics queries
* Report generation
* Export generation

## Frontend

**React + TypeScript + Inertia**

The current official Laravel React Starter Kit provides React 19, TypeScript, Inertia 3, shadcn/ui, Tailwind CSS, authentication, settings, layouts, and other application scaffolding.

The application should start from this official starter rather than building authentication and application scaffolding from scratch.

## Database

**PostgreSQL**

PostgreSQL will contain:

1. Application data
2. Synchronized Clockify data
3. Derived analytics data

## Queue

**Laravel Queue**

Long-running synchronization and export operations will run asynchronously.

## Scheduler

**Laravel Scheduler**

Used for automatic synchronization and maintenance jobs.

---

# 7. High-Level Architecture

```text
                         CLOCKIFY
                  ┌────────────────────┐
                  │                    │
                  │ Web / Android /    │
                  │ iOS                │
                  │                    │
                  └─────────┬──────────┘
                            │
                            ▼
                    ┌───────────────┐
                    │ Clockify API  │
                    └───────┬───────┘
                            │
                  ┌─────────▼─────────┐
                  │ Synchronization   │
                  │ Engine            │
                  │                   │
                  │ Rate Limiter      │
                  │ Pagination        │
                  │ Retry             │
                  │ Checkpoints       │
                  │ Change Detection  │
                  └─────────┬─────────┘
                            │
                            ▼
                  ┌───────────────────┐
                  │    PostgreSQL     │
                  │                   │
                  │ Source Data       │
                  │ Analytics Data    │
                  └─────────┬─────────┘
                            │
                            ▼
                  ┌───────────────────┐
                  │ Analytics Engine  │
                  └─────────┬─────────┘
                            │
                            ▼
                  ┌───────────────────┐
                  │ React Dashboard   │
                  │                   │
                  │ Charts            │
                  │ Reports           │
                  │ Exports           │
                  └───────────────────┘
```

---

# 8. Clockify Integration

Clockify provides a REST API for pulling data from workspaces. The API supports authentication through API keys or add-on tokens, pagination for list endpoints, and webhook-based notifications.

The integration should exist behind a dedicated service boundary.

Suggested structure:

```text
app/
└── Services/
    └── Clockify/
        ├── ClockifyClient
        ├── ClockifyAuthenticator
        ├── ClockifyRateLimiter
        ├── ClockifyPaginator
        ├── ClockifySyncService
        ├── ClockifyChangeService
        ├── ClockifyWebhookService
        └── ClockifySyncPlanner
```

Application code should never directly make arbitrary Clockify HTTP requests.

All requests pass through the integration layer.

---

# 9. Data Available From Clockify

## 9.1 Core principle

> **Do not model the Clockify report as the source of truth. Synchronize the
> underlying Clockify entities and build reports from our own relational model.**

Clockify describes its Detailed Report as a baseline dataset and its Entity
Changes API as the mechanism for keeping a local dataset synchronized. We target
the underlying entities — not the report output — so history, relationships and
rates are preserved in our own schema.

## 9.2 Entity inventory

Clockify's current Entity Changes API explicitly recognizes the following entity
types, each with **created / updated / deleted** semantics:

```text
CLIENTS
PROJECTS
TAGS
TASKS
SCHEDULED_ASSIGNMENT
TIME_ENTRY
TIME_ENTRY_RATE
TIME_ENTRY_CUSTOM_FIELD_VALUE
CUSTOM_FIELDS
USER
USER_GROUPS
INVOICES
APPROVAL_REQUESTS
BALANCE
HOLIDAYS
PTO_POLICY
TIME_OFF_REQUEST
```

The synchronization architecture should be capable of handling all of them. They
are organized into four business layers plus a synchronization-infrastructure
layer (§25).

## 9.3 Priority

| Entity                        | Priority        | Purpose                                  |
| ----------------------------- | --------------- | ---------------------------------------- |
| Workspace                     | **MVP**         | Root configuration and currency/timezone |
| User                          | **MVP**         | People dimension                         |
| Membership                    | **MVP**         | User/workspace relationship + rates      |
| Client                        | **MVP**         | Client dimension                         |
| Project                       | **MVP**         | Primary reporting dimension              |
| Project Member                | **MVP**         | User/project relationship + rates        |
| Task                          | **MVP**         | Work categorization                      |
| Tag                           | **MVP**         | Flexible categorization                  |
| Time Entry                    | **MVP**         | **Primary historical fact**              |
| Time Entry Rate               | **MVP**         | Historical billing/cost calculations     |
| Custom Field                  | **MVP**         | Metadata definition                      |
| Time Entry Custom Field Value | **MVP**         | Historical custom metadata               |
| User Custom Field Value       | **MVP**         | User/team metadata                       |
| User Group                    | **MVP / Phase 2** | Team/group analytics                   |
| Scheduled Assignment          | Phase 2         | Planned vs actual, capacity              |
| Holiday                       | Phase 2         | Capacity/utilization                     |
| PTO Policy                    | Phase 2         | PTO analytics                            |
| Time Off Request              | Phase 2         | PTO/availability                         |
| Balance                       | Phase 2         | Historical PTO balances                  |
| Approval Request              | Phase 2         | Approval analytics                       |
| Invoice                       | Phase 2         | Billing/invoice analytics                |
| Invoice Item                  | Phase 2         | Invoice-level analytics                  |
| Deleted Entity                | **MVP**         | Correct synchronization                  |
| Raw Record                    | **MVP**         | Recovery/debugging                       |
| Sync Run                      | **MVP**         | Sync observability                       |
| Sync Job                      | **MVP**         | Resumable historical imports             |
| Entity Change                 | **MVP**         | Incremental synchronization              |

`Deleted Entity` is MVP because Clockify's synchronization APIs explicitly
distinguish created/updated/deleted records. Without deletion handling the local
historical dataset silently diverges from Clockify.

## 9.4 MVP core

For the historical analytics MVP the absolute core is:

```text
Time Entry + User + Client + Project + Task + Tag + Rates + Custom Field Values
```

with deletion/change tracking around them. Everything else can be added later
without changing the fundamental architecture.

Because a workspace has been demonstrated to answer 5-year-old 31-day report
windows, the ingestion model is designed from day one to preserve **long-lived
historical relationships** rather than treating Clockify's 31-day report window
as the data-model boundary.

---

# 10. Historical Import

Historical import is a core onboarding feature.

During onboarding, the user selects the desired history.

```text
Import historical data

○ Last 1 year

○ Last 2 years

○ Last 5 years

○ All available history

○ Custom range
```

Example:

```text
User selects:

Last 5 years

        ↓

Calculate date range

        ↓

Inspect workspace

        ↓

Create synchronization plan

        ↓

Queue import jobs

        ↓

Rate-limited execution

        ↓

PostgreSQL

        ↓

Analytics available
```

---

# 11. Synchronization Planner

The planner converts a large historical range into manageable synchronization jobs.

Example:

```text
Requested:

2021-09-01 → 2026-09-23

        ↓

Synchronization plan

2021-09 → 2022-01
2022-01 → 2022-05
2022-05 → 2022-09
...
2026-05 → 2026-09
```

The partition size should be dynamic.

It should consider:

* Endpoint
* Pagination
* Result volume
* Workspace size
* API budget
* Historical import progress
* Previous request performance

The system should never assume:

> One month = one API request.

Pagination must always be handled independently.

Clockify's REST API supports pagination for list endpoints, with page and page-size parameters and a `Last-Page` response header.

---

# 12. Rate-Limit-Aware API Client

The application must have one centralized API rate limiter.

```text
                 ┌────────────────┐
                 │ Sync Request   │
                 └───────┬────────┘
                         ↓
                 ┌────────────────┐
                 │ Rate Limiter   │
                 └───────┬────────┘
                         ↓
                Request permitted?
                   /          \
                 NO            YES
                 │              │
                 ↓              ↓
              Wait          Clockify
              /retry           API
```

The rate limiter should be configurable per connection/workspace.

Clockify's current documentation states that its REST API allows **50 requests/second per add-on on one workspace when using `X-Addon-Token`**. Different authentication methods or endpoint policies may have different constraints, so the application should not hard-code a universal limit.

Configuration should therefore look conceptually like:

```text
api_rate_limit
requests_per_second
burst_limit
cooldown
```

The rate limiter should operate conservatively below the known source limit.

---

# 13. API Usage Tracking

API usage should be tracked internally.

Suggested table:

```text
clockify_api_usage

id
workspace_id

window_started_at
window_ends_at

requests_used
requests_remaining
limit

last_request_at
updated_at
```

The exact accounting mechanism should match the applicable Clockify API limit.

The backend is the authoritative source for the usage indicator.

---

# 14. API Usage UI

API usage should be visible but unobtrusive.

The navbar can display:

```text
Clockify API
24 remaining
```

or:

```text
API 24/30
```

depending on the applicable limit.

Clicking the indicator opens a small popover:

```text
Clockify API Usage

Current window
────────────────────

Used              6
Remaining        24
Limit            30

Resets in        42 min

Last request
2:14 PM
```

The UI must not imply a fixed limit if the connected API credential uses a different limit.

---

# 15. Sync Now

Manual synchronization is a first-class feature.

The dashboard should provide:

```text
Clockify Sync

Last synced:
12 minutes ago

Data through:
September 23, 2026

[ Sync Now ]
```

When clicked:

```text
Sync Now
   ↓
Check API budget
   ↓
Determine required changes
   ↓
Create sync run
   ↓
Queue jobs
   ↓
Rate-limited execution
   ↓
Update local data
   ↓
Refresh analytics
```

**Sync Now must not mean "re-import everything."**

It should normally perform an incremental synchronization.

---

# 16. Manual Sync and API Limits

A manual sync must use the exact same rate limiter as automatic synchronization.

There must be a single shared API budget:

```text
                  API Budget
                      │
          ┌───────────┼───────────┐
          ↓           ↓           ↓
       Manual       Daily       Webhook
        Sync         Sync        Sync
          │           │           │
          └───────────┼───────────┘
                      ↓
                 Rate Limiter
                      ↓
                  Clockify
```

This prevents separate synchronization mechanisms from accidentally exceeding the source API's limits.

---

# 17. Sync Priority

Synchronization jobs can have priorities.

```text
Manual Sync          HIGH
Webhook-triggered    HIGH
Daily Sync           NORMAL
Reconciliation       LOW
```

Priority affects queue ordering only.

It **never bypasses the API rate limiter**.

---

# 18. Daily Automatic Synchronization

The application should perform automatic synchronization on a regular schedule.

The default can be once per day.

Example:

```text
01:00 AM
   ↓
Determine changes
   ↓
Fetch data
   ↓
Update PostgreSQL
   ↓
Recalculate affected analytics
```

A one-day freshness delay is acceptable for the initial product.

The exact schedule can eventually be configurable.

---

# 19. Rolling Reconciliation

Daily synchronization alone is insufficient because users can modify historical Clockify entries.

Therefore, the system should periodically reconcile a rolling historical window.

Example:

### Daily

```text
Today
Yesterday
Previous 7 days
```

### Weekly

```text
Previous 30–31 days
```

This catches:

* Edited time entries
* Deleted entries
* Restored entries
* Changed projects
* Changed tasks
* Changed tags
* Other recent modifications

---

# 20. Entity Changes API

Clockify currently documents an experimental `/entities/updated` endpoint that retrieves entities updated within a specified date range. It supports multiple entity types and is specifically documented as a mechanism for keeping locally stored data synchronized.

This should be considered for incremental synchronization.

The integration should isolate experimental Clockify functionality behind an adapter so it can be changed if Clockify modifies the API.

## 20.1 Change model

```text
CREATED
UPDATED
DELETED
```

Every detected change is recorded locally as an **entity change**
(`clockify_entity_changes`) so ingestion is decoupled from application, and can
be replayed, audited and observed.

## 20.2 Deletion handling

Clockify exposes an **Entities Deleted** API returning records such as:

```text
deletedAt
document
documentCode
id
```

A deletion must never be reduced to a soft `deleted = true` flag on the entity
alone. Deletions are recorded in `clockify_deleted_entities` and applied to the
normalized model so historical reports stay consistent:

```text
Clockify project deleted
        ↓
clockify_deleted_entities
        ↓
normalized model updated
        ↓
historical reports stay consistent
```

Without this, a deleted Clockify project would continue to exist in our database
and silently corrupt historical reports.

## 20.3 Incremental sync flow

```text
last checkpoint
      ↓
GET /entities/updated?from=…&to=…
      ↓
GET /entities/deleted?from=…&to=…
      ↓
clockify_entity_changes
      ↓
rate-limited fetch of changed entities
      ↓
upsert / delete in the normalized model
      ↓
advance checkpoint
```

---

# 21. Webhooks

Clockify provides webhooks for real-time notifications of various events.

The API documentation currently lists webhook events including:

```text
NEW_TIME_ENTRY
TIME_ENTRY_UPDATED
TIME_ENTRY_DELETED
TIME_ENTRY_RESTORED
TIME_ENTRY_SPLIT

NEW_PROJECT
PROJECT_UPDATED
PROJECT_DELETED

NEW_TASK
TASK_UPDATED
TASK_DELETED

NEW_CLIENT
CLIENT_UPDATED
CLIENT_DELETED

NEW_TAG
TAG_UPDATED
TAG_DELETED

USER_UPDATED
USER_DELETED_FROM_WORKSPACE
USER_JOINED_WORKSPACE

NEW_INVOICE
INVOICE_UPDATED

EXPENSE_CREATED
EXPENSE_UPDATED
EXPENSE_DELETED

TIME_OFF_REQUESTED
TIME_OFF_REQUEST_UPDATED
...
```

Clockify currently allows up to three webhooks on the Free plan.

Webhooks should be treated as an optimization rather than the only synchronization mechanism.

---

# 22. Webhook Architecture

```text
Clockify
   │
   │ webhook
   ▼
Webhook Endpoint
   │
   ▼
Validate webhook
   │
   ▼
Create sync event
   │
   ▼
Queue
   │
   ▼
Rate Limiter
   │
   ▼
Clockify API
   │
   ▼
PostgreSQL
```

Webhook handlers should be lightweight.

They should acknowledge the webhook quickly and delegate processing to the queue.

---

# 23. Sync Checkpoints

Every synchronization job must be resumable.

Suggested model:

```text
clockify_sync_runs

id
workspace_id

trigger
status

started_at
completed_at

records_created
records_updated
records_deleted

api_requests_used

error
```

Individual jobs can additionally maintain:

```text
clockify_sync_jobs

id
sync_run_id
entity_type

range_start
range_end

page
page_size

records_processed

status
last_error
```

If a worker fails at page 37:

```text
page 37
   ↓
failure
   ↓
retry
   ↓
resume
```

The complete import should not restart.

Alongside `clockify_sync_runs` and `clockify_sync_jobs`, the synchronization
infrastructure maintains `clockify_entity_changes`, `clockify_deleted_entities`
and `clockify_raw_records` (§25.24–25.26). Together these make the pipeline
observable, replayable and recoverable.

---

# 24. Idempotency

Every synchronized entity should use the Clockify ID as an external identifier.

Example:

```text
clockify_time_entries

id
clockify_id UNIQUE
workspace_id
...
```

Synchronization should use upsert semantics.

```text
Clockify Entry
      ↓
clockify_id
      ↓
UPSERT
      ↓
Existing?
  /       \
YES       NO
 │         │
UPDATE    INSERT
```

Repeated imports must never create duplicate records.

The upstream `clockify_id` is the only join key across Clockify and our schema;
internal IDs are never sent to Clockify. A record's absence from a page is
**not** a deletion — deletions arrive exclusively through the deleted-entities
feed (§20.2), so paged imports stay idempotent and resumable.

---

# 25. Data Model

## 25.1 Layering

The model separates facts, dimensions, context and infrastructure so historical
relationships and rates are preserved rather than flattened into one wide table.

```text
                         CLOCKIFY
                             │
                             ▼
                      Sync / Ingestion
                             │
             ┌───────────────┴───────────────┐
             ▼                               ▼
     Raw Clockify Records            Normalized Model
     (clockify_raw_records)                  │
              ┌──────────────────────────────┼──────────────────────────────┐
              ▼                              ▼                              ▼
          Dimensions                       Facts                        Context
              │                              │                              │
    Users / Projects               Time Entries                Custom Fields
    Clients / Tasks                Entry Rates                 PTO
    Tags / Groups                                              Scheduling
              │                              │                              │
              └──────────────────────────────┼──────────────────────────────┘
                                             ▼
                                     Analytics Engine
                                             │
                                             ▼
                                    Reports / Dashboard
```

### Layer 1 — Core analytical facts

```text
clockify_time_entries
clockify_time_entry_rates
```

### Layer 2 — Dimensions

```text
clockify_workspaces
clockify_users
clockify_memberships
clockify_clients
clockify_projects
clockify_project_members
clockify_tasks
clockify_tags
clockify_user_groups
clockify_user_group_members
```

### Layer 3 — Metadata / context

```text
clockify_custom_fields
clockify_time_entry_custom_field_values
clockify_user_custom_field_values

clockify_scheduled_assignments

clockify_holidays
clockify_pto_policies
clockify_time_off_requests
clockify_balances

clockify_approval_requests
```

### Layer 4 — Commercial

```text
clockify_invoices
clockify_invoice_items
clockify_expenses
```

### Synchronization infrastructure

```text
clockify_sync_runs
clockify_sync_jobs
clockify_entity_changes
clockify_deleted_entities
clockify_raw_records
clockify_api_usage
```

### Application tables

```text
users
clockify_connections
```

Every synchronized table carries `workspace_id` (internal FK to
`clockify_workspaces`), the upstream `clockify_id` (unique), `created_at`,
`updated_at`, `synced_at` and, where useful, `raw_data`.

## 25.2 Workspace (`clockify_workspaces`)

The workspace is Clockify's root boundary, synchronized as a dimension. In our
single-tenant model (§40) it is **not** a tenant boundary.

```text
id
clockify_id
name
currency
time_zone
week_start
default_billable
default_hourly_rate
default_cost_rate
active
raw_data
created_at
updated_at
synced_at
```

## 25.3 Users (`clockify_users`)

Identity of a person in Clockify, kept separate from workspace membership
(§25.4) because membership and rates change over time.

```text
id
workspace_id
clockify_id

name
email
status

profile_picture_url

timezone
week_start
working_days
work_capacity

created_at
updated_at
synced_at

raw_data
```

## 25.4 Workspace memberships (`clockify_memberships`)

A user's relationship to a workspace, target entity and rates. Preserved so
historical user/workspace/project rates are never overwritten blindly.

```text
id
workspace_id
user_id

membership_type
membership_status

target_type
target_id

hourly_rate_amount
hourly_rate_currency

cost_rate_amount
cost_rate_currency

created_at
updated_at

raw_data
```

## 25.5 Clients (`clockify_clients`)

```text
id
workspace_id
clockify_id

name
email
address
note
currency

archived
archived_at

created_at
updated_at
synced_at

raw_data
```

Hierarchy: `Client → Project → Task → Time Entry`. A project belongs to one
client; a client may have many projects.

## 25.6 Projects (`clockify_projects`)

```text
id
workspace_id
clockify_id

client_id

name
color
note

status
archived
archived_at

billable

public
private

billable_rate_amount
billable_rate_currency

cost_rate_amount
cost_rate_currency

estimated_hours
estimated_cost

created_at
updated_at
synced_at

raw_data
```

Project membership is stored separately (§25.7) because different users on the
same project may have different rates.

## 25.7 Project members (`clockify_project_members`)

```text
id
project_id
user_id

membership_type
membership_status

hourly_rate_amount
hourly_rate_currency

cost_rate_amount
cost_rate_currency

created_at
updated_at

raw_data
```

## 25.8 Tasks (`clockify_tasks`)

Tasks are a real reporting dimension, not display metadata.

```text
id
workspace_id
project_id
clockify_id

name
status

assignee_user_id

billable

estimated_hours

billable_rate_amount
billable_rate_currency

cost_rate_amount
cost_rate_currency

created_at
updated_at
completed_at

raw_data
```

## 25.9 Tags (`clockify_tags`, `clockify_time_entry_tags`)

```text
clockify_tags
-------------
id
workspace_id
clockify_id

name

archived
archived_at

created_at
updated_at

raw_data


clockify_time_entry_tags
------------------------
time_entry_id
tag_id
```

Tags are stored relationally — never as a JSON array on the time entry — so that
hours by tag, users by tag, projects by tag, tag trends and billable vs
non-billable by tag remain queryable.

## 25.10 Time entries

See §26. This is the primary historical fact table.

## 25.11 Time entry rates (`clockify_time_entry_rates`)

Rates are **historical facts**. A 2023 entry must use the rate that applied to
it, not today's project rate.

```text
id
workspace_id
clockify_id

time_entry_id

user_id
project_id
task_id

billable_rate_amount
billable_rate_currency

cost_rate_amount
cost_rate_currency

created_at
updated_at

raw_data
```

## 25.12 Custom fields (`clockify_custom_fields`)

```text
id
workspace_id
clockify_id

name
description

type

entity_type

status

required
only_admin_can_edit

workspace_default_value

created_at
updated_at

raw_data
```

Supported field types include `Text`, `Number`, `Link`, `Switch`, `Select` and
`Select Multiple`.

## 25.13 Time entry custom field values (`clockify_time_entry_custom_field_values`)

```text
id

workspace_id

time_entry_id
custom_field_id

value

created_at
updated_at

raw_data
```

Kept as its own table — not JSON on the entry — because the Entity Changes API
treats `TIME_ENTRY_CUSTOM_FIELD_VALUE` as a separate entity.

## 25.14 User custom field values (`clockify_user_custom_field_values`)

```text
id
workspace_id
user_id
custom_field_id

value

created_at
updated_at

raw_data
```

Useful dimensions: department, location, employee type, team, cost center.

## 25.15 User groups (`clockify_user_groups`, `clockify_user_group_members`)

```text
clockify_user_groups
--------------------
id
workspace_id
clockify_id
name
status
created_at
updated_at
raw_data


clockify_user_group_members
---------------------------
user_group_id
user_id
```

Unlocks hours by team, utilization by team and billable percentage by team.

## 25.16 Scheduled assignments (`clockify_scheduled_assignments`)

Not required for basic time analytics, but designed now to unlock scheduled vs
actual hours, utilization, capacity and over/under-allocation.

```text
id
workspace_id
clockify_id

user_id
project_id
task_id

start_date
end_date

hours_per_day

start_time

billable

published
recurring

include_non_working_days

note

created_at
updated_at

raw_data
```

## 25.17 Holidays (`clockify_holidays`)

```text
id
workspace_id
clockify_id

name

start_date
end_date

color

created_at
updated_at

raw_data
```

Needed for expected working hours, capacity, utilization and attendance.

## 25.18 PTO policies (`clockify_pto_policies`)

```text
id
workspace_id
clockify_id

name

type
accrual_method

annual_allowance

created_at
updated_at

raw_data
```

Exact fields follow the API response rather than a guessed schema. The
architectural point is that `PTO_POLICY` is an explicit Clockify entity.

## 25.19 Time-off requests (`clockify_time_off_requests`)

```text
id
workspace_id
clockify_id

user_id
pto_policy_id

start_date
end_date

status

duration

reason

approved_by

created_at
updated_at

raw_data
```

## 25.20 Balances (`clockify_balances`)

A snapshot-style entity rather than a conventional fact table. Snapshots are
retained for historical PTO analytics.

```text
id
workspace_id

user_id
pto_policy_id

balance
unit

as_of

created_at
updated_at

raw_data
```

## 25.21 Approval requests (`clockify_approval_requests`)

```text
id
workspace_id
clockify_id

user_id

type

period_start
period_end

status

submitted_at
approved_at
rejected_at

created_at
updated_at

raw_data
```

## 25.22 Invoices (`clockify_invoices`, `clockify_invoice_items`)

Phase 2, unless the product's scope includes billing analytics.

```text
clockify_invoices
-----------------
id
workspace_id
clockify_id

client_id
project_id

number

status

currency

issue_date
due_date

subtotal
tax
total

created_at
updated_at

raw_data


clockify_invoice_items
----------------------
id
invoice_id
clockify_id

description
quantity
unit_price
total
```

## 25.23 Expenses (`clockify_expenses`)

Phase 2, retained from the earlier scope.

```text
id
workspace_id
clockify_id

user_id
project_id
task_id

category

amount
currency

date

billable

notes

created_at
updated_at

raw_data
```

## 25.24 Deleted entities (`clockify_deleted_entities`)

```text
id
workspace_id

entity_type
clockify_id

deleted_at

document_code

raw_data
```

Essential for synchronization — see §20.2.

## 25.25 Entity changes (`clockify_entity_changes`)

Not a Clockify entity; this is **our** synchronization infrastructure.

```text
id
workspace_id

entity_type
clockify_id

change_type

detected_at
source_at

processed_at

raw_data
```

Where `change_type` is `CREATED`, `UPDATED` or `DELETED`.

## 25.26 Raw records (`clockify_raw_records`)

An additional layer between Clockify and the normalized model:

```text
id
workspace_id

entity_type
clockify_id

payload

payload_hash

source
fetched_at

created_at
updated_at
```

Not necessarily exposed to the application. Its purpose is recovery: if Clockify
changes a response shape, or a field turns out to be needed after the fact, the
raw payload can rebuild the normalized entity.

```text
Clockify → Raw record → Normalized entity → Analytics
```

Retention may keep the current raw payload plus optional change history rather
than every historical version forever.

## 25.27 Sync runs, jobs and usage

```text
clockify_sync_runs
------------------
id
workspace_id
trigger
status
started_at
completed_at
records_created
records_updated
records_deleted
api_requests_used
error


clockify_sync_jobs
------------------
id
sync_run_id
entity_type
range_start
range_end
page
page_size
records_processed
status
last_error


clockify_api_usage
------------------
id
workspace_id
window_started_at
window_ends_at
requests_used
requests_remaining
limit
last_request_at
updated_at
```

The earlier names `sync_runs`, `sync_jobs`, `clockify_assignments` and
`clockify_time_entry_custom_fields` are superseded by `clockify_sync_runs`,
`clockify_sync_jobs`, `clockify_scheduled_assignments` and
`clockify_time_entry_custom_field_values` respectively.

---

# 26. Time Entry Model

The time entry is the central analytical fact.

Conceptually:

```text
clockify_time_entries

id
workspace_id
clockify_id

user_id

project_id
task_id

description

start_at
end_at
duration_seconds

billable

type

time_zone

is_locked
is_in_progress

approval_status

cost_amount
cost_currency

billable_amount
billable_currency

clockify_created_at
clockify_updated_at

created_at
updated_at
synced_at

raw_data
```

Where Clockify exposes them, `source` and `external_reference` may also be
stored.

Tags are **not** stored as a JSON array here. They live in
`clockify_time_entry_tags` (§25.9). Per-entry rates live in
`clockify_time_entry_rates` (§25.11) and custom-field values in
`clockify_time_entry_custom_field_values` (§25.13) — each is a historical fact
and must not be flattened onto the entry.

Clockify's API exposes time-entry information including project, task, tags,
billable status, descriptions, rates, custom fields, time interval, type and
related metadata. Clockify's Entity Changes documentation uses time entries as
the baseline dataset and identifies each record with a `timeEntryId`.

---

# 27. Raw Data vs Analytics Data

The database should clearly distinguish three tiers:

## Raw data

```text
clockify_raw_records
```

The unparsed upstream payloads. Used for recovery, debugging and re-normalization
if Clockify changes its response shape (§25.26).

## Source data

```text
clockify_*
```

These represent synchronized Clockify records normalized into our relational
model (dimensions, facts and context).

## Derived data

```text
daily_user_metrics
daily_project_metrics
monthly_user_metrics
monthly_project_metrics
monthly_client_metrics
```

Derived data can be recalculated without re-importing Clockify.

```text
Clockify
   ↓
Raw records
   ↓
Normalized entities
   ↓
Derived analytics
```

---

# 28. Analytics Engine

Initial metrics:

### Time

* Total tracked hours
* Billable hours
* Non-billable hours
* Average daily hours
* Average weekly hours
* Average monthly hours

### Projects

* Hours per project
* Project distribution
* Project trends
* Project growth

### Users

* Hours per user
* Billable hours per user
* User/project distribution
* Activity trends

### Clients

* Hours per client
* Client/project distribution
* Client trends

---

# 29. Historical Comparisons

Historical comparison should be a core feature.

Examples:

```text
September 2026
vs
September 2025
```

```text
Q3 2026
vs
Q3 2025
```

```text
2026 YTD
vs
2025 YTD
```

Supported metrics:

```text
Tracked hours
Billable hours
Non-billable hours
Projects
Users
Average daily hours
Utilization
```

---

# 30. Dashboard

The dashboard should be visually polished but intentionally simple.

Example:

```text
September 2026

┌────────────────┐ ┌────────────────┐
│ Tracked        │ │ Billable       │
│ 1,284h         │ │ 942h           │
└────────────────┘ └────────────────┘

┌────────────────┐ ┌────────────────┐
│ Users          │ │ Projects       │
│ 18             │ │ 27             │
└────────────────┘ └────────────────┘

        Hours Over Time

        ───────────────

        Project Distribution

        ───────────────

        Billable vs Non-Billable
```

The dashboard should avoid excessive cards and visual noise.

---

# 31. Reporting

Reports should support:

```text
Today
This week
This month
Last month
This quarter
This year
Last year
Last 2 years
Last 5 years
All time
Custom range
```

Grouping:

```text
User
Project
Client
Task
Tag
Date
Month
Quarter
Year
```

Eventually, multiple dimensions:

```text
Project
  └── User
       └── Month
```

---

# 32. Exports

Initial formats:

* CSV
* XLSX
* PDF

PDF should be treated as a proper report rather than a browser screenshot.

Example:

```text
Project Report
September 2026

Project              Hours       Billable
------------------------------------------
Project A             412h        381h
Project B             281h        211h
Project C             193h        182h
```

Large exports should execute asynchronously.

---

# 33. Export Architecture

```text
User
 ↓
Generate Report
 ↓
Queue Export Job
 ↓
Query PostgreSQL
 ↓
Generate File
 ↓
Store Temporary File
 ↓
Notify User
 ↓
Download
```

Exports must not query Clockify directly.

---

# 34. Onboarding UX

Proposed flow:

```text
Welcome
   ↓
Connect Clockify
   ↓
Select Workspace
   ↓
Choose Historical Range
   ↓
Review Import
   ↓
Start Import
   ↓
Dashboard
```

During import:

```text
Importing your Clockify data

████████████████░░░░ 78%

Records imported:
1,284,921

Current period:
March 2024

You can leave this page.
The import will continue in the background.
```

---

# 35. Sync Status UX

The user should always understand data freshness.

Example:

```text
Clockify Sync

● Healthy

Last synced:
Today, 01:14 AM

Data through:
September 23, 2026

Historical data:
January 2021 → September 2026

Next automatic sync:
Tomorrow, 01:00 AM

[ Sync Now ]
```

---

# 36. Sync Progress UX

When a manual or initial sync is running:

```text
Syncing Clockify...

Fetching changes

████████████░░░░

1,842 records processed

API requests:
9 used

API remaining:
41
```

The user can leave the page.

The synchronization continues in the background.

---

# 37. API Budget UX

Navbar:

```text
Clockify   API 41
```

Expanded:

```text
Clockify API

Requests
──────────────

Used              9
Remaining        41
Limit             50

Last request
2:14 PM

[ View Sync Activity ]
```

The exact values must come from the backend rate-limit/accounting system.

---

# 38. Sync Failure UX

If synchronization fails:

```text
Clockify Sync

⚠ Sync delayed

Last successful sync:
Today, 01:14 AM

Reason:
Clockify API temporarily unavailable.

We'll retry automatically.

[ Retry Now ]
```

Technical error details should be available to administrators without exposing raw API errors unnecessarily to normal users.

---

# 39. Security

Clockify credentials are sensitive.

The application must:

* Encrypt API credentials at rest.
* Never expose credentials to React.
* Never send API keys to the browser.
* Perform all Clockify API requests server-side.
* Restrict access to the authenticated user's own data.
* Support disconnecting a Clockify connection.
* Avoid logging secrets.
* Protect webhook endpoints.
* Validate webhook authentication/token information.
* Apply authorization policies to all workspace data.

---

# 40. Single-Tenant Model

> **Decision:** Multi-tenancy is **out of scope**. Evoriq is a single-tenant
> application; there are no organizations or tenant scopes. This supersedes the
> earlier multi-organization design.

Data belongs to the application as a whole. Where a record has an owner, it is
tracked with a plain foreign key (e.g. `user_id`) and derived from the
authenticated user — never accepted from the client.

Clockify workspace IDs remain external identifiers and are kept separate from
our internal IDs to avoid tight coupling to Clockify's identity system.

Clockify workspaces are synchronized as a dimension (`clockify_workspaces`,
§25.2), so multiple workspace connections can exist within the single tenant —
but a workspace is **not** a tenant boundary and no `tenants` table exists.

---

# 41. Reliability

The synchronization system should tolerate:

* Network failures
* Clockify API failures
* Rate limiting
* Worker crashes
* Duplicate responses
* Partial imports
* Edited records
* Deleted records
* Restored records
* Out-of-order updates

Required properties:

```text
Idempotent
Retryable
Resumable
Rate-limited
Observable
Eventually consistent
```

---

# 42. Observability

Track:

```text
sync_runs_total
sync_runs_failed

sync_duration

clockify_api_requests
clockify_api_errors
clockify_rate_limit_events

records_created
records_updated
records_deleted

queue_depth
failed_jobs
```

Internal health page:

```text
Sync Health       Healthy
API Errors        0
Failed Jobs       0
Last Sync         01:14 AM
Records Synced    1,284,921
```

---

# 43. Initial MVP

## Authentication

* Registration
* Login
* Password reset
* Email verification
* Profile/settings

The Laravel React Starter Kit already provides the foundation for these capabilities.

## Clockify

* Connect workspace
* Historical import
* Select import range
* Initial sync
* Daily sync
* Sync Now
* API usage indicator
* Sync status
* Retry failed synchronization

## Data

* Workspace
* Users + workspace memberships
* Clients
* Projects + project members
* Tasks
* Tags (relational)
* Time entries
* Time entry rates
* Custom fields + time entry/user custom field values
* Entity changes + deleted entities (sync correctness)
* Raw records (recovery)

## Dashboard

* Total hours
* Billable hours
* Non-billable hours
* User distribution
* Project distribution
* Time trend

## Reports

* User report
* Project report
* Client report
* Custom date range
* Historical comparison
* Grouping

## Export

* CSV
* PDF

---

# 44. Post-MVP

Potential additions:

### Analytics

* Utilization
* Project profitability
* Cost analysis
* Rate analysis
* Forecasting
* Advanced trends

### Clockify data

* Expenses
* Invoices
* PTO
* Holidays
* Attendance
* Approvals
* Custom fields
* Assignments

### Reporting

* Saved reports
* Scheduled reports
* Email reports
* Custom dashboards
* Report templates

### Integrations

Potential future sources:

```text
Clockify
Toggl
Harvest
CSV
Other time-tracking platforms
```

Multi-source support should not be part of the MVP.

---

# 45. Product Identity

The product should not be marketed primarily as:

> "A Clockify clone."

A better positioning is:

> **Historical time analytics and reporting for Clockify.**

The product's differentiator is the combination of:

```text
Clockify tracking
       +
Historical data
       +
Simple analytics
       +
Beautiful reporting
       +
Low-impact synchronization
```

---

# 46. Naming

Working candidates:

* Timebase
* TimeLens
* TimeScope
* Trackbase
* Timelytics
* Hourbase
* Chrona
* Chronicle
* Tracklytics

The name should be validated against:

* Domain availability
* Existing SaaS products
* Trademark conflicts
* Searchability
* Pronunciation
* Brandability

No name should be finalized until those checks are completed.

---

# 47. Final Architecture

```text
                         CLOCKIFY
                            │
               ┌────────────┴────────────┐
               │                         │
          REST API                    Webhooks
               │                         │
               └────────────┬────────────┘
                            │
                    ┌───────▼────────┐
                    │ Sync Engine    │
                    │                │
                    │ Planner        │
                    │ Rate Limiter   │
                    │ Pagination     │
                    │ Retry          │
                    │ Checkpoints    │
                    │ Change Sync    │
                    │ Deletion Sync  │
                    └───────┬────────┘
                            │
                    ┌───────▼────────┐
                    │   PostgreSQL   │
                    │                │
                    │ Raw Records    │  clockify_raw_records
                    │ Sync State     │  runs / jobs / changes
                    │                │  / deleted entities
                    │ Normalized     │  dimensions + facts
                    │ Entities       │  + context
                    │ Analytics      │
                    └───────┬────────┘
                            │
                    ┌───────▼────────┐
                    │ Analytics      │
                    │ Engine         │
                    └───────┬────────┘
                            │
                    ┌───────▼────────┐
                    │ React +        │
                    │ Inertia        │
                    │                │
                    │ Dashboard      │
                    │ Reports        │
                    │ Charts         │
                    │ Exports        │
                    └────────────────┘
```

---

# 48. Core Technical Decision

The application must **not** be designed around Clockify's report interface.

Instead:

```text
Clockify
    ↓
Raw records (clockify_raw_records)
    ↓
Normalized entities (dimensions + facts + context)
    ↓
PostgreSQL
    ↓
Our analytics
    ↓
Our reports
```

Clockify remains the upstream source.

PostgreSQL becomes our analytical data store.

The application controls:

* Historical retention
* Querying
* Aggregation
* Charts
* Comparisons
* Reports
* Exports
* Data freshness
* Synchronization strategy

This makes the product independent of Clockify's reporting UX while preserving the user's existing Clockify workflow.

---

# 49. MVP Success Criteria

The MVP should be considered successful when a user can:

1. Create an account.
2. Connect a Clockify workspace.
3. Select a historical import range.
4. Start the import.
5. Leave the application while the import continues.
6. See synchronization progress.
7. See API usage and remaining budget.
8. Manually trigger Sync Now.
9. Continue using Clockify normally.
10. Return later and see synchronized data.
11. View historical analytics beyond the normal Clockify reporting window.
12. Compare different periods.
13. Generate reports.
14. Export reports.
15. Trust that synchronization is running safely in the background.

The product should feel like a **small, polished analytics application**, not a large enterprise time-management suite.
