# Architecture

The kit follows a strict **Service–Repository** architecture so product work
can start on day one: controllers are thin, services own business logic and
transactions, repositories own every database query, and DTOs are the typed
currency between the HTTP layer and services.

```text
HTTP request
  └─ Controller ──(FormRequest validation)──> Service ──> Repository ──> Eloquent Model ──> PostgreSQL
                                                 │
                                                 ├─ DTO / FilterDTO cross the Service boundary
                                                 └─ Jobs (queue) for long-running sync / exports

Inertia page  <── Controller (Inertia::render) <── Service <── Repository
React (resources/js)  <── typed props (Resources / arrays) <── Controller
```

## Backend layers

| Layer              | Path                                                                 | Responsibility                              |
| ------------------ | -------------------------------------------------------------------- | ------------------------------------------- |
| Model              | `app/Models/{Model}.php`                                              | Relations, casts, scopes                    |
| Migration          | `database/migrations/*_create_{table}_table.php`                      | Schema + indexes                            |
| Factory            | `database/factories/{Model}Factory.php`                               | Test data                                   |
| Enum               | `app/Enums/{Name}.php`                                                | Fixed value sets                            |
| DTO                | `app/DTOs/{Module}/{Module}DTO.php`                                   | Typed input for services                    |
| FilterDTO          | `app/DTOs/{Module}/{Module}FilterDTO.php`                             | Typed list filters                          |
| Repository contract| `app/Repositories/Contracts/{Module}RepositoryInterface.php`          | Query contract                              |
| Repository         | `app/Repositories/{Module}/{Module}Repository.php`                    | All Eloquent access                         |
| Service            | `app/Services/{Module}/{Module}Service.php`                           | Business logic + transactions               |
| Request            | `app/Http/Requests/{Module}/{Action}Request.php`                      | Validation                                  |
| Resource           | `app/Http/Resources/{Module}/{Module}Resource.php`                    | Response / prop shaping                     |
| Controller         | `app/Http/Controllers/{Module}/{Module}Controller.php`                | Thin HTTP layer                             |
| Policy             | `app/Policies/{Model}Policy.php`                                      | Authorization                               |
| Job                | `app/Jobs/{Module}/{Job}.php`                                         | Async work                                  |
| Exception          | `app/Exceptions/{Name}.php`                                           | Domain errors                               |

Key rules:

- **Controllers** — validate with a `FormRequest`, authorize with a policy,
  call one service method, return a response. No queries, no business logic.
- **Services** — inject repository interfaces (never concrete repositories or
  models), own all `DB::transaction(...)` boundaries, and never run queries.
- **Repositories** — all Eloquent access; `create`/`update` take plain arrays.
- **DTOs** — `readonly` classes with `fromRequest()` and `toArray()`.
- **Jobs** — thin: resolve a service and delegate. Long-running jobs are
  idempotent and resumable.
- **Single-tenant** — ownership is a plain `user_id` foreign key derived from
  `auth()->id()` in the service, never trusted from the client.

External integrations belong under `app/Services/{Vendor}/**` as
infrastructure — never inside module services.

## Audit trail

Model changes are collected automatically via the `LogsActivity` trait with a
selective allowlist; named domain events are recorded with
`AuditLogService::record()` from services. Channels are `auth`, `security`,
`rbac`, `settings` and `domain`, each with configurable retention. Secrets are
redacted centrally, and every row written in one request/job shares a
`correlation_id`.

## Exports & imports (Data Processing Center)

Every long-running operation is tracked with `App\Models\DataProcessingJob`
and runs as a queued job on the `heavy` channel. The single producer entry
point is `DataProcessingJobService::dispatch(JobRequest)`. Entities are
registered on `App\Enums\DataEntity`; exporter/importer classes live in
`app/Exports` and `app/Imports`. The UI lives at `/activity`.

## RBAC

Permissions are declared in `config/permission-registry.php` and read through
`App\Registry\PermissionRegistry`. Roles are seeded as **Super Admin** (all
permissions + `Gate::before` bypass), **Admin** and **Member** (system roles,
`is_system = true`). Authorize every action with `Gate::authorize(...)`.

## Frontend

| Concern        | Path                              |
| -------------- | --------------------------------- |
| Page           | `resources/js/pages/{name}.tsx`   |
| Feature UI     | `resources/js/components/{feature}/` |
| UI primitives  | `resources/js/components/ui/*` (shadcn, do not edit) |
| Layout         | `resources/js/layouts/**`         |
| Hook           | `resources/js/hooks/use-*.ts(x)`  |
| Utility        | `resources/js/lib/*.ts`           |
| Types          | `resources/js/types/*.ts`         |
| Generated      | `resources/js/{actions,routes,wayfinder}/**` (do not edit) |

- Data flows **server → page props**; type the props the controller sends.
- Navigate with `Link` / `router` and **Wayfinder** helpers — never hard-code
  URLs.
- Forms use Inertia's `<Form>`/`useForm` with **Zod** validation
  (`useZodForm`) mirroring the server-side `FormRequest`.
- Create/update happens in a `Dialog` opened from the list page, not a
  dedicated page.
- Styling is Tailwind utilities + shadcn/ui primitives with semantic tokens
  and dark-mode variants.
- Strict TypeScript: no `any`, no non-null assertions.

## Generated files — do not edit

- `resources/js/actions/**`, `resources/js/routes/**`,
  `resources/js/wayfinder/**` (Wayfinder — regenerate with `npm run build`)
- `resources/js/components/ui/*` (shadcn/ui — re-publish with
  `npx shadcn@latest add`)

The full, authoritative contract (including mail/queue reliability rules and
translation rules) lives in [`AGENTS.md`](../AGENTS.md) at the repository
root.
