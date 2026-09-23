# Technical Design — Clockify Analytics Platform

**Status:** Draft
**Version:** 0.2
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

The synchronization architecture should be capable of handling the major Clockify entities relevant to analytics.

Initial entities:

```text
Workspace
Users
Clients
Projects
Tasks
Tags
Time Entries
Custom Fields
```

Future entities:

```text
Expenses
Invoices
Approvals
Attendance
Time Off
Holidays
PTO
Assignments
Rates
```

Clockify's current API documentation also exposes an experimental Entity Changes API covering multiple entity types, including time entries, projects, tasks, clients, tags, users, invoices, approval requests, balances, holidays, PTO policies, and time-off requests.

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
sync_runs

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
sync_jobs

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

---

# 25. Data Model

Core tables:

```text
workspaces

clockify_users
clockify_user_groups

clockify_clients

clockify_projects
clockify_tasks
clockify_tags

clockify_time_entries

clockify_custom_fields
clockify_time_entry_custom_fields

clockify_expenses
clockify_invoices

clockify_time_off_requests
clockify_holidays
clockify_pto_policies

clockify_assignments
```

Additional application tables:

```text
users
clockify_connections

sync_runs
sync_jobs
clockify_api_usage
```

---

# 26. Time Entry Model

The time entry is the central analytical fact.

Conceptually:

```text
clockify_time_entries

id
clockify_id

workspace_id
user_id
project_id
task_id

description

start_at
end_at
duration_seconds

billable
type

cost_rate
hourly_rate

tag_ids

is_locked

clockify_created_at
clockify_updated_at

synced_at
```

Clockify's API exposes time-entry information including project, task, tags, billable status, descriptions, rates, custom fields, time interval, type, and related metadata.

---

# 27. Raw Data vs Analytics Data

The database should clearly distinguish:

## Source data

```text
clockify_*
```

These represent synchronized Clockify records.

## Derived data

```text
daily_user_metrics
daily_project_metrics
monthly_user_metrics
monthly_project_metrics
monthly_client_metrics
```

Derived data can be recalculated without re-importing Clockify.

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

* Users
* Clients
* Projects
* Tasks
* Tags
* Time entries

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
                    └───────┬────────┘
                            │
                    ┌───────▼────────┐
                    │   PostgreSQL   │
                    │                │
                    │ Source Data    │
                    │ Sync State     │
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
Raw synchronized data
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
