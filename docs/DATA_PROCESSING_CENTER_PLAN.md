# Plan — Data Processing Center (async jobs: imports, exports, report generation)

**Status:** Implemented (Phases 0–3) — Phase 4 hardening & polish
**Scope:** One application-wide surface — the **Job Center** — for every long-running
background operation: file **imports**, data **exports**, and future **report
generation** (e.g. "generate a 5-year PDF"). It ships the reusable producer API,
the queued jobs, live progress UI, artifact handling, in-app notifications, a
queue-confirmation dialog, and a sidebar badge. User import/export is the first
consumer; report generation is a first-class future consumer.
**Stack:** Laravel 13 · PHP 8.4 · Inertia 3 · React 19 · TypeScript · Tailwind 4 ·
shadcn/ui · PostgreSQL (SQLite in tests) · `maatwebsite/excel` 4.0 · Queue (`heavy`)
**Related:** `TDR.md` (§10, §32, §33, §35–38), `AGENTS.md` (§7.9, §7.14, §8),
`docs/NOTIFICATION_SYSTEM_PLAN.md`, `docs/UI_BUILD_PLAN.md`,
`.agents/skills/add-export`, `.agents/skills/generate-module`

> **Revision note (r1):** The export *pipeline* was ported from `frc-backend`
> (`DataProcessingJob`, enums, DTOs, repository, service, `ProcessExport`,
> `UserExport`, JSON controller, policy, cleanup, tests). It works end-to-end but
> has no product UI, no import side, and never notifies anyone.
>
> **Revision note (r2):** Per review, this is **not** a "user import/export
> screen". It is the **application's Data Processing Center**: every future
> long-running operation (report generation, large exports, imports) registers
> here. Therefore the plan promotes the entity registry to a generic `DataEntity`,
> adds a **producer dispatch API**, **report** operation type, **cancellation /
> retry / duplicate**, a **global queue-confirmation dialog**, and an
> **active-jobs sidebar badge**. The UI is specified as a reusable Job Center, not
> a one-off page.
>
> **Revision note (r3):** The permission model is refined. The center gets its own
> dedicated **`data-processing.*`** permissions; the global `export.*` group is
> reduced to a cross-entity **`export.create`**, a matching global
> **`import.create`** is added, and **module-level `{module}.export` /
> `{module}.import`** permissions (starting with **`user.export`** /
> **`user.import`**) provide fine-grained, per-entity control. The **User module
> integration is split into its own phase** (Phase 3) so the center is finished
> and generic first.

### Locked with the user

| # | Decision |
| --- | --- |
| 1 | Unified **Activity / Job Center** page at `/activity` listing **all** import, export and report jobs. |
| 2 | Import semantics: **create only, skip duplicates** (existing emails are reported as skipped). |
| 3 | Permissions: dedicated **`data-processing.*`** for the center; global **`export.create` / `import.create`**; module-level **`{module}.export` / `{module}.import`** (starting with `user.export` / `user.import`). |
| 4 | After queueing, show a **confirmation dialog**: "queued successfully → track it in Activity; we'll notify you when it's done." |
| 5 | The sidebar **Activities** item shows a **live badge** of active jobs. |
| 6 | The UI must be good enough to be the **permanent home for all async work**, including report generation. |
| 7 | Sequencing: finish the **Data Processing Center** first, then integrate the **User module** (import/export entry points) as a dedicated phase. |

---

## 1. Objective & vision

### 1.1 Objective

Give Evoriq a **single, beautiful, reusable Data Processing Center** that:

1. Runs **imports** (Users first), **exports** (CSV/XLSX), and later **reports**
   (PDF/CSV/XLSX) as queued jobs.
2. Lists every job — pending / running / completed / failed / cancelled — with
   live progress, in a GitHub-Actions-like UI.
3. Lets users **download artifacts** from the Job Center **and** from Files →
   Reports.
4. **Notifies** the owner in-app when a job finishes or fails.
5. Exposes a **one-liner producer API** so any future module (reports, large
   analytics dumps, data migrations) can queue work into the same place without
   touching the UI.
6. Shows a **confirmation dialog** on queue and a **sidebar badge** of active work.

### 1.2 The vision: one job center for the whole app

```text
                 ┌──────────────────────────────────────────────────────────┐
   future:       │               EVORIQ JOB CENTER  (/activity)             │
   reports ───┐  │                                                          │
   exports ───┼─▶│  Job Center UI  ←  DataProcessingJob list + live progress │
   imports ───┤  │     ▲                         ▲                          │
   sync ──────┘  │     │ poll/props              │ notifications            │
                 │  Producer API (one-liner)      │                          │
                 │     │                          │                          │
                 │  DataProcessingJobService.dispatch(JobRequest)           │
                 │     ├─ ProcessExport  ─ UserExport / ReportExport         │
                 │     ├─ ProcessImport  ─ UserImport                        │
                 │     └─ (future) ProcessSync, ProcessReport, …             │
                 └──────────────────────────────────────────────────────────┘
```

**Design consequence:** the UI renders **from job metadata**, never from
hard-coded entity knowledge. Adding a report later = add a `DataEntity` case +
an exporter; **zero UI changes**.

---

## 2. Non-goals (this plan)

- **Clockify synchronization.** That is the historical sync module (TDR §10–11),
  behind the Clockify boundary. It will *surface* its progress in this Job Center
  later, but it is not built here.
- Implementing report **content** (the 5-year PDF itself). This plan builds the
  container, operation type, producer API and UI that reports will use.
- Column-mapping import UI; importing entities other than Users (the registry
  makes this mechanical later).
- Multi-artifact jobs (a table of artifacts). v1 = one artifact per job; the
  resource shape is forward-compatible (see §6.4).
- Row-level import checkpointing; v1 is idempotent via duplicate-skip and safe to
  re-run.
- Frontend unit tests (no JS runner — `AGENTS.md` §8.10).

---

## 3. Current-state audit

### 3.1 What exists (and works)

| Area | State | Key files |
| --- | --- | --- |
| Job table | ✅ model + migration + factory + casts | `app/Models/DataProcessingJob.php`, `..._create_data_processing_jobs_table.php` |
| Enums | ✅ type (`import`/`export`), status (`pending`/`processing`/`completed`/`failed`) | `app/Enums/DataProcessingJobType.php`, `DataProcessingJobStatus.php` |
| DTOs / repo / service | ✅ filters, pagination, create, mark*, attachFile, download, delete, cleanup | `app/DTOs/DataProcessingJob/*`, `app/Repositories/DataProcessingJob/*`, `app/Services/DataProcessingJob/*` |
| Export job | ✅ `ProcessExport` on `heavy` | `app/Jobs/ProcessExport.php` |
| Exporters | ✅ `Exportable` + `UserExport` + `ExportEntity` registry | `app/Exports/**` |
| JSON endpoints | ✅ list/store/show/download/destroy | `app/Http/Controllers/Export/ExportController.php`, `routes/exports.php` |
| Policy | ✅ `export.*` | `app/Policies/DataProcessingJobPolicy.php` |
| Files browser | ✅ Files + Reports tabs, downloads jobs | `FileController.php`, `pages/files/index.tsx` |
| Notifications | ✅ full module (bell, feed, polling, preferences, targets) | `app/Services/Notification/**`, `resources/js/components/notification/**` |
| Notification types | ✅ `export.completed`/`export.failed` (declared, never produced) | `app/Enums/NotificationType.php` |
| Cleanup | ✅ scheduled daily, both types | `CleanupCompletedDataProcessingJobsCommand.php` |
| Flash toast | ✅ `router.on('flash')` → `sonner` | `resources/js/hooks/use-flash-toast.ts` |
| Sidebar groups | ✅ grouped nav + `useCan()` | `resources/js/components/app-sidebar.tsx`, `nav-main.tsx` |

### 3.2 Gaps this plan closes

| Gap | Detail |
| --- | --- |
| **No producer API** | Each caller must know the job class + repository; no single entry point. |
| **No import side** | No `ProcessImport`, `app/Imports/**`, `ImportEntity`, request/route, template. |
| **No generic registry** | `ExportEntity` is export-only; reports need a shared `DataEntity`. |
| **No report operation type** | `DataProcessingJobType` lacks `report`; no PDF path. |
| **No lifecycle control** | Can't cancel, retry, duplicate. |
| **No product UI** | Only the Files → Reports table; no live progress, detail, actions. |
| **No notifications** | `EXPORT_*` types never created; no import types. |
| **Fake export progress** | `markCompleted()` defaults `total_items=0`, so progress is always `0`. |
| **No import input columns** | Table stores output (`file_*`) but not the uploaded input. |
| **Permission model is export-only** | No center-level permissions, no global import, no per-entity (module-level) export/import. |
| **No badge / confirmation** | No active-job awareness in the shell; queueing gives only a toast. |

---

## 4. Locked decisions

| # | Question | Decision |
| --- | --- | --- |
| 1 | UI surface | **`/activity`** ("Activities") — the Job Center for all async work. Files → Reports remains the artifact browser. |
| 2 | Registry | Promote to a single generic **`DataEntity`** (replaces `ExportEntity` value-typing). |
| 3 | Operation types | `import`, `export`, **`report`** (report reuses the export pipeline with report exporters). |
| 4 | Statuses | `pending`, `processing`, `completed`, `failed`, **`cancelled`**. |
| 5 | Import semantics | Create only; **skip duplicates**; invited + invitation email (optional). |
| 6 | Progress | Real `total_items`/`processed_items`; `stage` label for multi-step reports; indeterminate bar when unknown. |
| 7 | Lifecycle | **Cancel** (pending/soft for processing), **Retry** (failed), **Duplicate**, **Delete**. |
| 8 | Live updates | Inertia `usePoll` partial reloads, active-only, visibility-aware, with a "Live/Paused" indicator. |
| 9 | Notifications | `NotificationService::create` targeted to the owner on terminal status (completed/failed/cancelled). |
| 10 | Queue confirmation | Global dialog driven by an Inertia flash (`job_queued`), mounted in the app layout. |
| 11 | Sidebar badge | Shared `activeJobs` prop → live count badge on Activities; refreshed by the existing poll. |
| 12 | Permissions | Center access via **`data-processing.*`**; global `export.create`/`import.create`; module-level `{module}.export`/`{module}.import`; owner-or-`.view.all` visibility. |
| 13 | Queue | All job classes run on `heavy` via `QueueRegistry`. |
| 14 | Artifact | Single artifact per job (export file / import report). Multi-artifact deferred. |
| 15 | Sequencing | Phase 1–2 = center (generic); **Phase 3 = User module integration**; Phase 4 = hardening. |

> See §11 for the full permission model and §19 for the phase breakdown.

---

## 5. Architecture & producer API

### 5.1 Flow

```text
Producer (controller, future report module, command, job)
   │  app(DataProcessingJobService::class)->dispatch(new JobRequest(...))
   ▼
DataProcessingJobService ──transaction──> Repository ──> data_processing_jobs
   │  after commit: DataProcessingJobDispatcher::dispatch($job)
   │        ├─ EXPORT / REPORT → ProcessExport   (heavy)
   │        └─ IMPORT          → ProcessImport   (heavy)
   ▼
ProcessExport / ProcessImport
   │  markProcessing(stage, total)  →  work  →  markProgress(...)
   │  attachArtifact(...)  →  markCompleted/Failed/Cancelled
   └─ notifyFinished($job)  →  NotificationService  →  bell + toast + feed

UI: ActivityController (Inertia)  ──props──>  pages/data-processing/index
      useJobPoll(active)  →  partial reload { jobs, stats, activeJobs }
```

### 5.2 Producer API (the one-liner)

`app/DTOs/DataProcessingJob/JobRequest.php`:

```php
final readonly class JobRequest
{
    /**
     * @param array<string, mixed> $parameters  entity-specific inputs (date range, filters…)
     */
    public function __construct(
        public DataEntity $entity,
        public DataProcessingJobType $type,
        public ?ExportFormat $format = null,
        public ?string $name = null,                 // human title; derived when null
        public array $parameters = [],
        public ?int $userId = null,
        public ?string $inputDisk = null,            // imports
        public ?string $inputPath = null,
        public ?string $originalFileName = null,
    ) {}
}
```

`DataProcessingJobService`:

```php
public function dispatch(JobRequest $request): DataProcessingJob
{
    return DB::transaction(function () use ($request): DataProcessingJob {
        $job = $this->repository->create([
            'type' => $request->type,
            'entity_type' => $request->entity,
            'format' => $request->format,
            'name' => $request->name,
            'filters' => $request->parameters,   // generic "parameters"
            'user_id' => $request->userId ?? auth()->id(),
            'input_disk' => $request->inputDisk,
            'input_path' => $request->inputPath,
            'original_file_name' => $request->originalFileName,
            'status' => DataProcessingJobStatus::PENDING,
        ]);

        return $job;
    });
    // dispatch happens after commit via DispatchDataProcessingJob (queue afterCommit)
}
```

- `createExport()` / `createImport()` become **thin wrappers** over `dispatch()`,
  keeping backwards compatibility for existing tests.
- `DataProcessingJobDispatcher` maps `type → job class`; **new operation types are
  one map entry + one job class**.
- The service derives `userId` from `auth()->id()` when not provided (never trusts
  the client — `AGENTS.md` §7.11).

### 5.3 Future report producer (illustrative, not built now)

```php
$job = $jobs->dispatch(new JobRequest(
    entity: DataEntity::TIME_ENTRIES,
    type: DataProcessingJobType::REPORT,
    format: ExportFormat::PDF,
    name: '5-year time report',
    parameters: ['from' => '2021-09-01', 'to' => '2026-09-01', 'group_by' => 'month'],
));
// → appears in the Job Center automatically; progress, stages, artifact,
//   notification and sidebar badge all come for free.
```

---

## 6. Data model changes

### 6.1 Migration — extend `data_processing_jobs`

`database/migrations/<ts>_extend_data_processing_jobs_table.php`:

```php
Schema::table('data_processing_jobs', function (Blueprint $table): void {
    // producer-supplied title + current step (great for multi-stage reports)
    $table->string('name')->nullable()->after('type');
    $table->string('stage')->nullable()->after('status');

    // imported source file (v1: import input)
    $table->string('input_disk')->nullable()->after('filters');
    $table->string('input_path')->nullable()->after('input_disk');
    $table->unsignedBigInteger('input_size')->nullable()->after('input_path');
    $table->string('input_mime_type')->nullable()->after('input_size');

    // cooperative cancellation
    $table->timestamp('cancel_requested_at')->nullable()->after('completed_at');

    $table->index(['user_id', 'status']);   // "my active jobs" badge query
});
```

- `file_*` = **output artifact**; `input_*` = **source file** (import).
- `filters` is documented as generic **parameters** (renaming the column is a
  future, non-breaking follow-up; the DTO already accepts arbitrary keys).

### 6.2 Enums

`DataProcessingJobType`:

```php
enum DataProcessingJobType: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';
    case REPORT = 'report';

    public function label(): string { /* Import | Export | Report */ }
    public function icon(): string  { /* upload | download | file-text */ }
    public function isFileGenerating(): bool { return $this !== self::IMPORT; }
}
```

`DataProcessingJobStatus` — add `CANCELLED = 'cancelled'`, update `label()` and
`isFinal()` (now `COMPLETED|FAILED|CANCELLED`).

### 6.3 Model (`app/Models/DataProcessingJob.php`)

- Fillable/casts/property docs for the new columns (`cancel_requested_at`, `name`,
  `stage`, `input_*`; `input_size` → integer).
- Cast `entity_type` to **`DataEntity`**.
- Helpers used by UI/policy/jobs:
  ```php
  public function isImport(): bool;
  public function isExport(): bool;
  public function isReport(): bool;
  public function isActive(): bool;                 // ! status->isFinal()
  public function isCancelled(): bool;
  public function cancellationRequested(): bool;    // cancel_requested_at !== null
  public function displayName(): string;            // name ?? "{Entity} {operation}"
  public function durationLabel(): ?string;
  public function progressPercentage(): int;        // existing
  public function stageLabel(): ?string;
  public function isDownloadable(): bool;           // existing
  ```
- Scopes: `active()`, `imports()`, `exports()`, `reports()`.

### 6.4 Artifact (forward-compatible)

v1 returns a single artifact from `file_*`. The resource exposes an **array** so a
future `data_processing_job_artifacts` table can return many without a UI change:

```php
'artifacts' => $this->artifactList(), // [{ key:'primary', label, file_name, size, mime, downloadable, expires_at }]
```

### 6.5 Factory

`DataProcessingJobFactory` gains states: `import()`, `report()`, `active()`
(`PROCESSING`, `total_items=100`, `processed_items=42`, `stage='Rendering'`),
`cancelled()`.

---

## 7. Generic registry — `DataEntity`

`app/Enums/DataEntity.php` (replaces `ExportEntity` as the persisted cast):

```php
enum DataEntity: string
{
    case USERS = 'users';
    // future: TIME_ENTRIES, PROJECTS, CLIENTS, REPORTS…

    public function label(): string;
    public function icon(): string;                 // lucide name
    public function supports(DataProcessingJobType $type): bool;
    public function formats(): array;               // allowed ExportFormat[]

    public function makeExporter(array $parameters): Exportable;       // export/report
    public function makeImporter(DataProcessingJob $job): Importable;  // import
    public function importHeadings(): array;        // template header
    public function importSampleRow(): array;       // example row shown in the template
    public function importColumnHelp(): array;      // ['email' => 'required, unique']
    public function importRules(): array;           // optional pre-validation

    /** Permission-module prefix, e.g. USERS => 'user' (maps to user.export/user.import). */
    public function permissionKey(): string;
}
```

v1: `USERS` supports `IMPORT` + `EXPORT` (report returns `unsupported`). Adding a
report entity later = one case + one exporter.

`ExportEntity`/`ImportEntity` are **removed** in favour of `DataEntity`; tests
referencing them are updated (values are unchanged, so no data migration).

---

## 8. Backend — export & report

### 8.1 Real progress

`app/Exports/Contracts/Exportable.php`:

```php
interface Exportable extends FromCollection, WithHeadings
{
    public function total(): int;                       // rows this export will produce
    default public function stage(): string { return 'Generating file'; } // optional
}
```

`UserExport` gets a shared `query()` used by both `collection()` and `total()`.

### 8.2 Job reports progress + stages

`ProcessExport`:

```php
$exporter = $entity->makeExporter($job->filters ?? []);
$total = $exporter->total();

$service->markProcessing($job, totalItems: $total, stage: $exporter->stage());
Excel::store($exporter, $filePath, $fileDisk);
$service->attachArtifact($job, $fileName, $filePath, $fileDisk);
$service->markCompleted($job, totalItems: $total, processedItems: $total);
$this->notifySafely($service, $job);
```

- `markProcessing(?int $totalItems, ?string $stage)`; `markProgress(int $processed, ?string $stage)`.
- Reports reuse this exact pipeline; a multi-stage report exporter calls
  `markProgress()`/updates `stage` between phases (data fetch → aggregate →
  render), which the Job Center timeline renders.
- `catch (Throwable)`: `markFailed()` + notify, then rethrow.

### 8.3 Resource enrichment

`DataProcessingJobResource`:

```php
'id','job_id',
'name' => $this->displayName(),
'type' => $this->type->value, 'type_label' => $this->type->label(), 'type_icon' => $this->type->icon(),
'status' => $this->status->value, 'status_label' => $this->status->label(),
'entity' => $this->entity_type?->value, 'entity_label' => $this->entity_type?->label(),
'entity_icon' => $this->entity_type?->icon(),
'format' => $this->format?->value, 'format_label' => $this->format?->label(),
'parameters' => $this->filters,
'stage' => $this->stage,
'progress' => ['total'=>$this->total_items,'processed'=>$this->processed_items,'percentage'=>$this->progressPercentage(),'indeterminate'=>$this->total_items===null],
'counts' => ['created'=>$this->success_count,'skipped'=>...,'failed'=>$this->error_count],
'errors' => $this->errors ?? [],
'error_message' => $this->error_message,
'artifacts' => $this->artifactList(),
'input_file_name' => $this->original_file_name,
'owner' => $this->whenLoaded('user', fn () => $this->user?->name),
'duration' => $this->durationLabel(),
'can' => [
    'cancel' => $this->isActive() && ! $this->cancellationRequested(),
    'retry' => $this->isFailed(),
    'duplicate' => true,
    'download' => $this->isDownloadable(),
    'delete' => true,
],
'started_at','completed_at','created_at',
```

`expires_at` per artifact = `completed_at + config('exports.cleanup_days')` days.

---

## 9. Backend — import

### 9.1 Contract & result

`app/Imports/Contracts/Importable.php`:

```php
/**
 * @extends ToCollection<int, Collection<int, mixed>>
 */
interface Importable extends ToCollection, WithHeadingRow, WithChunkReading, SkipsEmptyRows
{
    public function entity(): DataEntity;
    public function result(): ImportResult;
}
```

`app/Imports/ImportResult.php` — readonly `{ processed, created, skipped, failed, errors[] }`.

### 9.2 `UserImport`

- Chunked (`chunkSize = 500`), trims/normalizes rows, validates per row, **skips
  duplicates** (existing in DB or earlier in the file), creates invited users,
  optionally sends invitations, records every failure/skip with its row number.
- Calls an injected `onProgress(int $processed, ?string $stage)` closure after each
  chunk so `ProcessImport` writes progress live.
- Creation reuses user business logic: extract a public
  `UserService::invite(User $user): void` from the existing `sendInvitation()` so
  both `UserService` and `UserImport` share one invitation path.

### 9.3 Job

`ProcessImport` (mirrors `ProcessExport`):

```php
$service->markProcessing($job, stage: 'Reading file');
$total = ImportCounter::count($job->input_path, $job->input_disk);
$service->markProcessing($job, totalItems: $total, stage: 'Importing rows');

$importer = $job->entity_type->makeImporter($job);
Excel::import($importer, $job->input_path, $job->input_disk);

$result = $importer->result();
$service->storeImportReport($job, $result);           // CSV artifact when errors/skips exist
$service->markCompleted($job, $total, $result->processed,
    successCount: $result->created, errorCount: $result->failed, errors: $result->errors);
$this->notifySafely($service, $job);
```

- `ImportCounter` = CSV line count / XLSX `getHighestRow()-1`; `0` → indeterminate.
- **Cancellation-aware:** between chunks the job reloads `cancel_requested_at`;
  if set, it stops, writes a partial report and marks `CANCELLED`.

### 9.4 Upload & downloadable template

Every import dialog is **self-serve**: it offers a ready-to-fill template that is
generated from the entity registry, so the headings can never drift from what the
importer actually expects. This applies to **every** import entry point — the
generic center dialog *and* module dialogs (e.g. the Users page) — because they
share one component and one route.

- **Route:** `GET /activity/import/template/{entity}?format=csv|xlsx`
  (default `csv`), authorized with the same `create` ability as the import.
- **Always generated on the fly** (never a stored/committed file) from
  `DataEntity::importHeadings()`, so it stays in sync with the importer.
- **Contents:** a header row, one realistic **example row**, and (CSV) a leading
  `#` comment line documenting required vs optional columns. XLSX renders the
  same headings with a frozen header row.
- **Entity metadata drives it** — `importHeadings()`, `importSampleRow()` and
  `importColumnHelp()` on `DataEntity` (see §7).
- **UX:** the import dialog shows a prominent **Download template** control with
  a CSV/XLSX picker and a one-line hint per column; the chosen format
  pre-selects the dropzone's accepted file types.
- **Upload:** `StoreImportRequest` validates `entity_type` (enum), `file`
  (`csv,txt,xlsx`, ≤ 20 MB), `filters.send_invitations` (bool),
  `filters.default_role` (role name); the service stores it under
  `{exports.base_path}/imports` on `config('exports.disk')` and records
  `input_*` + `original_file_name`.

---

## 10. Notifications

### 10.1 Types

`NotificationType` adds:

```php
case IMPORT_COMPLETED = 'import.completed';
case IMPORT_FAILED    = 'import.failed';
case REPORT_COMPLETED = 'report.completed';   // reserved for the report module
case REPORT_FAILED    = 'report.failed';
case JOB_CANCELLED    = 'job.cancelled';       // optional
```

Labels, priorities (success/critical), icons (`file-up`, `file-x`, `file-text`,
`ban`). `NotificationPreferenceService` validates muted types against the enum
automatically, so the settings UI needs no change.

### 10.2 Producer

`DataProcessingJobService::notifyFinished(DataProcessingJob $job): void`:

```php
if ($job->user_id === null) return;

$type = NotificationType::forJob($job->type, $job->status); // match type+status → enum
$entity = $job->entity_type?->label() ?? 'Data';
$done = $job->isCompleted();

$dto = new NotificationDTO(
    type: $type,
    title: $done ? "{$entity} {$job->type->label()} ready" : "{$entity} {$job->type->label()} failed",
    body: $done
        ? ($job->isDownloadable()
            ? __(':count records ready. Download your file.', ['count' => $job->processed_items ?? 0])
            : __('Finished processing :count records.', ['count' => $job->processed_items ?? 0]))
        : ($job->error_message ?? __('Something went wrong. Open the job for details.')),
    actionUrl: $job->isDownloadable() ? route('exports.download', $job) : route('activity.index', ['job_id' => $job->job_id]),
    data: ['job_id' => $job->job_id, 'type' => $job->type->value, 'downloadable' => $job->isDownloadable()],
    targets: [new NotificationTargetDTO(NotificationTargetType::USER, $job->user_id)],
    createdBy: $job->user_id,
);

$this->notifications->create($dto, $job->user_id);
```

- `NotificationType::forJob()` centralises the type mapping (extensible for reports).
- Both jobs call it in a `try/catch` so a notification failure never fails the job.
- Runs on the worker after the status write (already committed) — safe.
- The existing bell poll toasts it; `action_url` deep-links to the artifact or the
  job drawer.

---

## 11. Permissions & policies

The model has **three tiers**, so the center can be opened to the right people
without over-granting the ability to run arbitrary work.

| Tier | Purpose | Example |
| --- | --- | --- |
| **Center** (`data-processing.*`) | Access to the Data Processing Center itself: view jobs, manage them. | `data-processing.view` |
| **Global operation** (`export.*`, `import.*`) | Cross-entity capability: run an export/import **of any entity**. | `export.create`, `import.create` |
| **Module operation** (`{module}.*`) | Per-entity capability, the fine-grained control. | `user.export`, `user.import` |

### 11.1 Registry

> **Naming.** The permission group is `data-processing` (it mirrors the
> `DataProcessingJob` domain), while the user-facing page is branded
> **Activities** at `/activity`. For perfect symmetry you could instead use
> `activity.*` for the permissions or move the route to `/data-processing` — say
> the word and I'll align them.

`config/permission-registry.php` — add the center group, reduce `export` to a
global create, and add a global `import`:

```php
'data-processing' => [
    'permissions' => [
        ['name' => 'data-processing.view',     'group' => 'data-processing', 'description' => 'Open the Data Processing Center and view own jobs'],
        ['name' => 'data-processing.view.all', 'group' => 'data-processing', 'description' => 'View every user\'s processing jobs'],
        ['name' => 'data-processing.manage',   'group' => 'data-processing', 'description' => 'Cancel, retry or duplicate any processing job'],
        ['name' => 'data-processing.delete',   'group' => 'data-processing', 'description' => 'Delete any processing job and its files'],
    ],
],

'export' => [
    'permissions' => [
        ['name' => 'export.create', 'group' => 'export', 'description' => 'Export any entity (global)'],
        // export.view / export.view.all / export.delete are RETIRED — superseded by data-processing.*
    ],
],

'import' => [
    'permissions' => [
        ['name' => 'import.create', 'group' => 'import', 'description' => 'Import any entity (global)'],
    ],
],
```

And on the **User module** (`user` group), add the two module-level permissions:

```php
['name' => 'user.export', 'group' => 'user', 'description' => 'Export users'],
['name' => 'user.import', 'group' => 'user', 'description' => 'Import users'],
```

- `php artisan permission:sync` upserts the new permissions and grants them to the
  Super Admin.
- The retired `export.view` / `export.view.all` / `export.delete` rows are removed
  by the sync command (add a cleanup arm there if it doesn't already prune).
- `RoleSeeder` is updated to grant the recommended defaults (see §11.4).

### 11.2 Authorization resolution

**Create a processing job** (`create`, with the operation type + entity):

| Operation | Allowed when the user has … |
| --- | --- |
| Export **users** | `user.export` **OR** `export.create` |
| Import **users** | `user.import` **OR** `import.create` |
| Export/report **any entity** | `export.create` (global) |
| Import **any entity** | `import.create` (global) |
| Report `{module}` (future) | `{module}.report` **OR** `export.create` |

The module permission is resolved from `DataEntity::permissionKey()` — e.g.
`USERS` → `user`, so the required module permission is `user.export` /
`user.import` (matching the existing singular `user.*` group).

**Open / browse the center:**

| Ability | Allowed when … |
| --- | --- |
| `viewAny` (open the center) | `data-processing.view` **OR** `data-processing.view.all` |
| `view` (a single job) | owner (`job.user_id === user.id`) **OR** `data-processing.view.all` |
| `download` (artifact) | same as `view` |
| `manage` (cancel / retry / duplicate) | owner **OR** `data-processing.manage` |
| `delete` | owner **OR** `data-processing.delete` |

### 11.3 Policy

`DataProcessingJobPolicy`:

```php
public function viewAny(User $user): bool
{
    return $user->hasAnyPermission(['data-processing.view', 'data-processing.view.all']);
}

public function view(User $user, DataProcessingJob $job): bool
{
    return $job->user_id === $user->id
        || $user->hasPermissionTo('data-processing.view.all');
}

/**
 * @param  array{0: DataProcessingJobType, 1: DataEntity}  $args
 */
public function create(User $user, DataProcessingJobType $type, DataEntity $entity): bool
{
    $module = "{$entity->permissionKey()}.{$type->value}";   // e.g. user.export
    $global = $type === DataProcessingJobType::IMPORT ? 'import.create' : 'export.create';

    return $user->hasPermissionTo($module) || $user->hasPermissionTo($global);
}

public function manage(User $user, DataProcessingJob $job): bool
{
    return $job->user_id === $user->id || $user->hasPermissionTo('data-processing.manage');
}

public function download(User $user, DataProcessingJob $job): bool
{
    return $this->view($user, $job);
}

public function delete(User $user, DataProcessingJob $job): bool
{
    return $job->user_id === $user->id || $user->hasPermissionTo('data-processing.delete');
}
```

Controller usage:

```php
Gate::authorize('create', [DataProcessingJob::class, DataProcessingJobType::EXPORT, $entity]);
Gate::authorize('manage', $job);
Gate::authorize('delete', $job);
```

### 11.4 Recommended role grants (`RoleSeeder`)

| Role | Grants |
| --- | --- |
| **Super Admin** | everything (`Gate::before`). |
| **Admin** | `data-processing.view`, `data-processing.view.all`, `data-processing.manage`, `data-processing.delete`, `export.create`, `import.create`, `user.export`, `user.import`. |
| **Member** | `data-processing.view`, `user.export`, `user.import` (own jobs only; no cross-entity, no manage-others). |

> Because a user who queues a job is told to "track it in Activities", any role
> granted a module/global **create** permission should also be granted
> `data-processing.view`. This is enforced by the seeder defaults above.

### 11.5 Impact / migration notes

- `ExportController` and `ActivityController` move from `export.*` to the new
  abilities; `FileController`'s Reports gate becomes
  `Gate::authorize('viewAny', DataProcessingJob::class)` → `data-processing.view`.
- `DataProcessingJobFeatureTest` and `tests/Feature/Rbac/PoliciesTest.php` are
  updated in the same PR.
- `HandleInertiaRequests`'s shared `can` map gains `data-processing` boolean(s) so
  the sidebar can gate the Activities item (`useCan()` unchanged).

---

## 12. The Job Center UI (the crown jewel)

> Goal: **the best async-jobs UI in the app**, reusable for every future
> operation. It must feel calm with zero jobs, delightful with one, and powerful
> with thousands.

### 12.1 Design principles

| Principle | Meaning |
| --- | --- |
| **Calm** | Generous whitespace, one accent, no chrome noise. Color is reserved for status and progress. |
| **Live** | Progress moves; the list updates itself; a clear "Live" state you can pause. |
| **Legible** | Every job states *what*, *how far*, *how long*, *what next* in one scan line. |
| **Capable** | Download, cancel, retry, duplicate, delete, inspect — without leaving the page. |
| **Universal** | Rendered from metadata; reports, exports and imports look native with no new code. |
| **Accessible** | Status conveyed by icon + label (never color alone); keyboard navigable; `aria-live`. |

### 12.2 Page anatomy

```text
┌───────────────────────────────────────────────────────────────────────────────┐
│  Activities                                             [ Import ] [ Export ]  │
│  Everything running in the background — imports, exports and reports.         │
│                                                                               │
│  ┌ Queued ┐  ┌ Running ┐  ┌ Completed ┐  ┌ Failed ┐  ┌ Cancelled ┐             │
│  │   12   │  │    1    │  │    482    │  │    3   │  │     0     │             │
│  └────────┘  └─────────┘  └───────────┘  └────────┘  └───────────┘             │
│                                                                               │
│  [ All ] [ Imports ] [ Exports ] [ Reports ]   [ Status ▾ ]   🔍 Search…   ⏸ Live│
│                                                                               │
│  ▾ Just now                                                                   │
│   📥  Users import          Import · CSV       ◐ Processing                    │
│       ▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░  1,842 / 5,000   ·  2m 12s   ·  ETA 3m  │
│       Reading file → Importing rows                                    ⬇ ⋯   │
│                                                                               │
│   📤  Users export          Export · XLSX      ● Completed                     │
│       1,204 rows  ·  2.4s  ·  expires in 6 days                        ⬇ ⋯   │
│                                                                               │
│   📄  Time entries report   Report · PDF       ◐ Processing   ●●●○○ (3/5)      │
│       Fetching · Aggregating · Rendering                              ⋯        │
│                                                                               │
│  ▾ Yesterday                                                                  │
│   📥  Users import          Import · CSV       ✕ Failed  12 errors     ⬇ ⋯   │
└───────────────────────────────────────────────────────────────────────────────┘
```

- **Stat strip** — clickable counts filter the list; counts come from `stats` prop.
- **Filter bar** — type segmented control (All/Imports/Exports/Reports), status
  select, debounced search; **Live/Paused** toggle controls polling.
- **Day grouping** — "Just now / Today / Yesterday / <date>" sticky headers.
- **Active jobs pinned** to the top group and highlighted with a subtle accent rail.
- **Row** — status glyph · type icon · title · type/format chips · progress or
  result summary · duration/ETA · expiry · hover actions (`download`, `⋯`).
- **Click a row** → **detail drawer**.
- **Responsive** — below `md`, rows become cards; filters stack; actions move into
  the card footer.

**Import dialog — template first (every import dialog, center and module):**

```text
┌ Import users ──────────────────────────────────────────────── ✕ ┐
│ Step 1 · Get the template                                       │
│   [ CSV ▾ ]  [ ⬇ Download template ]   · 3 columns, 1 example   │
│                                                               │
│ Step 2 · Upload your file                                       │
│   ┌────── drop a .csv or .xlsx file, or click to browse ──────┐ │
│   │            ⬆  Drag & drop here                            │ │
│   └───────────────────────────────────────────────────────────┘ │
│                                                               │
│ Step 3 · Options                                                │
│   [x] Send invitation emails to new users                       │
│   Default role: [ Member ▾ ]                                    │
│                                                               │
│ Step 4 · Preview  (headers matched ✓ · 1,204 rows detected)     │
│                                            [ Cancel ] [ Import ]│
└───────────────────────────────────────────────────────────────┘
```

- The template control is the shared `import-template-download.tsx` (format
  picker → `template/{entity}` route); the chosen format also sets the dropzone's
  `accept`.
- Uploaded headers are matched against `importHeadings()`; unmatched columns are
  flagged **before** the user can submit, so a wrong/missing column never fails a
  background job silently.

### 12.3 Detail drawer

```text
┌ Users import ───────────────────────────────────────────── ✕ ┐
│  Import · CSV · users.xlsx · started 09:41 · 2m 14s            │
│                                                               │
│  Timeline                                                     │
│  ● Queued          09:41:02                                   │
│  │  ◐ Reading file  09:41:03                                  │
│  │  ◐ Importing…    1,842 / 5,000                              │
│  └─ ● Completed     09:43:16                                   │
│                                                               │
│  Result    Created 1,204 · Skipped 640 · Failed 12            │
│  Artifact  ⬇ users_import_report.csv · 18 KB · expires in 6d   │
│                                                               │
│  Parameters   send_invitations: on · default_role: Member     │
│                                                               │
│  Issues  (downloadable CSV)                                    │
│  ┌ Row ─ Type ─────── Message ───────────────────────────────┐ │
│  │ 42    duplicate     Already exists: ada@example.com       │ │
│  │ 118   validation    The email field must be valid         │ │
│  └───────────────────────────────────────────────────────────┘ │
│                                                               │
│  [ Download ]  [ Run again ]  [ Retry ]  [ Stop ]  [ Delete ] │
│  Job ID · 7c1e…  (copy)                                       │
└───────────────────────────────────────────────────────────────┘
```

- Timeline is generated from `status`, `stage`, `started_at`, `completed_at`, plus
  `stage` transitions for multi-step reports (`●` completed steps from a
  `stages` list the report producer supplies).
- **Run again** duplicates the job (same entity/type/parameters/input);
  **Retry** re-queues a failed job; **Stop** requests cancellation; all gated by
  `job.can`.
- Error table has per-row copy and a "download all" action.

### 12.4 Components (`resources/js/components/data-processing/`)

| Component | Responsibility |
| --- | --- |
| `job-status-badge.tsx` | Pill: pending (amber), processing (blue + pulse), completed (green), failed (red), cancelled (muted). |
| `job-status-rail.tsx` | Thin colored left rail for the row state. |
| `job-type-icon.tsx` | Tinted rounded square, `download`/`upload`/`file-text`. |
| `job-progress.tsx` | Accessible bar; shimmer when indeterminate; `processed / total` + %. |
| `job-timeline.tsx` | Queued → stages → terminal; renders `stage` transitions. |
| `job-row.tsx` / `job-card.tsx` | Desktop row / mobile card (same data contract). |
| `job-list.tsx` | Day grouping, sticky headers, active-first ordering. |
| `job-filters.tsx` | Type segmented + status select + search + Live/Paused toggle. |
| `job-stat-cards.tsx` | Clickable status counts (`stat-card.tsx`). |
| `job-detail-sheet.tsx` | Slide-over drawer with timeline, params, artifacts, issues, actions. |
| `job-issue-table.tsx` | Errors/skips with row, type, message, copy/download. |
| `job-empty-state.tsx` | Onboarding: "Nothing running. Import or export something." |
| `job-expiry.tsx` | "expires in N days" countdown. |
| `export-dialog.tsx` | Entity + format + live parameter summary. |
| `import-dialog.tsx` | Dropzone + **downloadable template** (CSV/XLSX picker + per-column hints) + options. |
| `import-template-download.tsx` | Reusable template picker/button shared by every import dialog (center + module). |
| `report-dialog.tsx` | Placeholder/gated for the future report module (hidden until built). |
| `job-queued-dialog.tsx` | Global post-queue confirmation (§13). |
| `job-utils.ts` | Label/format/grouping/ETA/[[relative-time]] helpers (no date-fns dependency). |

### 12.5 Hooks

| Hook | Responsibility |
| --- | --- |
| `use-job-poll.ts` | `usePoll` partial reload `{ jobs, stats, activeJobs }`, active-only, visibility-aware, exposes `{ live, pause, resume }`. |
| `use-job-transitions.ts` | Watches job ids → terminal status; fires toast + optimistic badge decrement. |
| `use-job-queued.tsx` | Subscribes to the Inertia `flash` event for `job_queued` and exposes it to the dialog. |

### 12.6 Page & routing

`routes/activity.php`:

```php
Route::middleware(['auth','verified'])->prefix('activity')->name('activity.')->group(function () {
    Route::get('/', [ActivityController::class,'index'])->name('index');
    Route::post('export', [ActivityController::class,'storeExport'])->name('export');
    Route::post('import', [ActivityController::class,'storeImport'])->name('import');
    Route::get('import/template/{entity}', [ActivityController::class,'template'])->name('template');
    Route::post('{dataProcessingJob}/cancel', [ActivityController::class,'cancel'])->name('cancel');
    Route::post('{dataProcessingJob}/retry',  [ActivityController::class,'retry'])->name('retry');
    Route::post('{dataProcessingJob}/duplicate',[ActivityController::class,'duplicate'])->name('duplicate');
    Route::delete('{dataProcessingJob}', [ActivityController::class,'destroy'])->name('destroy');
});
```

- `index` renders `data-processing/index` with `jobs`, `stats`, `activeJobs`,
  `filters`, `options`.
- `storeExport`/`storeImport`: `Gate::authorize('create', [DataProcessingJob::class, $type, $entity])`,
  `service->dispatch(...)`, flash `job_queued` + `toast`, `back()`. Imports also handle the upload.
- `template/{entity}` streams the generated CSV/XLSX template (gated by the
  import `create` ability).
- `cancel`/`retry`/`duplicate`: `Gate::authorize('manage', $job)`; `destroy`:
  `Gate::authorize('delete', $job)`; flash toast; `back()`.
- Download reuses the existing **`exports.download`** route so there is one
  download endpoint for Activity *and* Files → Reports.

### 12.7 Polish & delight

- **ETA** computed client-side from `processed/total` and elapsed (smoothed); shown
  only when determinate.
- **Micro-interactions**: number count-up on new stats, progress shimmer,
  status-change flash animation, "Live" dot pulse, row enter animation (respect
  `prefers-reduced-motion`).
- **Command palette** (`⌘K`/`Ctrl K`): "Go to Activities", "New export", "New
  import", "Search jobs".
- **Density toggle** (comfortable/compact) persisted per user via the settings
  pattern.
- **Empty / loading / error** states for every surface; skeleton rows on partial
  reload; "Live updates paused" banner when the tab is hidden.
- **Dark mode** on every surface using semantic tokens; status colors defined once
  as tokens and reused by chips, rails, and progress.
- **Copy job id / link**, and deep link `?job_id=` auto-opens the drawer (used by
  notifications).

---

## 13. Global queue-confirmation dialog

After `storeExport` / `storeImport` (or any future producer triggered by a user),
the shell shows a friendly confirmation — **app-wide**, so report buttons added
later get it for free.

- **Trigger:** server `Inertia::flash('job_queued', ['name' => $job->displayName(), 'job_id' => $job->job_id]);`
  (same flash channel as `use-flash-toast.ts`).
- **Component:** `job-queued-dialog.tsx`, mounted **globally** in
  `layouts/app/app-layout.tsx` via `use-job-queued.tsx`.
- **Copy:**

```text
┌───────────────────────────────────────────────┐
│                     ✓                          │
│            Your job is queued                  │
│                                                │
│  “Users export” is running in the background.  │
│  Follow its progress in Activities — we’ll     │
│  notify you when it’s ready.                   │
│                                                │
│            [ View Activities ]   [ Close ]     │
└───────────────────────────────────────────────┘
```

- **Behaviour:** auto-focus the primary button; `Esc`/backdrop closes; "View
  Activities" navigates to `/activity` (optionally with `?job_id=`); the sidebar
  badge increments immediately (optimistic) in addition to the flash.
- A quiet `sonner` toast still fires (`use-flash-toast`), so the confirmation is
  reinforced even if the dialog is dismissed.

---

## 14. Sidebar badge (active jobs)

- **Shared prop:** `HandleInertiaRequests::share` adds
  `'activeJobs' => fn (): int => $this->activeJobCount($request)` — counts pending +
  processing jobs visible to the user (own jobs, or all when they hold
  `data-processing.view.all`), and returns `0` for users without
  `data-processing.view`.
- **Nav item:** `app-sidebar.tsx` adds **Activities**
  (`can('data-processing.view') || can('data-processing.view.all')`) under
  **Workspace**, carrying an optional badge.
- **`NavItem` type** (`types/navigation.ts`) gains `badge?: number`; `nav-main.tsx`
  renders a small count badge (99+ cap) and pulses when > 0.
- **Freshness:** extend `use-notification-poll.ts`'s `only` array to
  `['notifications','feed','activeJobs']` so the badge stays current app-wide
  without a second poll.
- **Optimistic:** queuing a job increments the badge instantly via the flash /
  `use-job-transitions`; it decrements when a job reaches a terminal state.

---

## 15. Files → Reports integration

- Keep the Reports tab; filter to `status=completed` + `downloadable=true`.
- Each row links "Open in Activities" (deep link `?job_id=`) so the two surfaces
  connect.
- Import error reports appear here too (they are just artifacts).
- Remove any hard-coded "export" wording in favour of `type_label`.

---

## 16. Lifecycle actions (cancel / retry / duplicate)

| Action | Availability | Behaviour |
| --- | --- | --- |
| **Cancel / Stop** | `pending` | Mark `CANCELLED`; the job returns immediately when it starts (checks status). |
| **Stop** | `processing` | Set `cancel_requested_at`; long-running/report jobs check between chunks/stages and finish by marking `CANCELLED` with a partial report. |
| **Retry** | `failed` | Reset to `pending`, clear `error_message`/`errors`/`completed_at`, re-dispatch the same job class. |
| **Duplicate / Run again** | any terminal | New job copying type/entity/format/parameters (imports also require the input file to still exist; else disabled). |
| **Delete** | any | Delete output artifact, input file, and the row. |

- Implemented in `DataProcessingJobService` (`cancel`, `retry`, `duplicate`) with a
  `manage` policy ability; jobs are **cancellation-aware** at chunk/stage
  boundaries.

---

## 17. File structure summary

```text
Backend
  database/migrations/<ts>_extend_data_processing_jobs_table.php        NEW
  app/Models/DataProcessingJob.php                                      EDIT
  database/factories/DataProcessingJobFactory.php                       EDIT
  app/Enums/DataEntity.php                                              NEW (replaces ExportEntity)
  app/Enums/DataProcessingJobType.php                                   EDIT (+REPORT, icon/label)
  app/Enums/DataProcessingJobStatus.php                                 EDIT (+CANCELLED)
  app/Enums/NotificationType.php                                        EDIT (+import/report/cancel)
  app/DTOs/DataProcessingJob/JobRequest.php                             NEW (producer API)
  app/DTOs/DataProcessingJob/DataProcessingJobFilterDTO.php             EDIT (type=report, mine/all)
  app/Exports/Contracts/Exportable.php                                  EDIT (+total/stage)
  app/Exports/UserExport.php                                            EDIT (query()/total())
  app/Exports/ImportTemplateExport.php                                  NEW (downloadable CSV/XLSX template)
  app/Exports/UserImportReportExport.php                                NEW
  app/Imports/Contracts/Importable.php                                  NEW
  app/Imports/ImportResult.php                                          NEW
  app/Imports/UserImport.php                                            NEW
  app/Imports/Support/ImportCounter.php                                 NEW
  app/Jobs/ProcessExport.php                                            EDIT (progress/stage/cancel/notify)
  app/Jobs/ProcessImport.php                                            NEW
  app/Jobs/DataProcessingJobDispatcher.php                              NEW (type → job map)
  app/Http/Requests/DataProcessingJob/StoreImportRequest.php            NEW
  app/Http/Controllers/DataProcessingJob/ActivityController.php         NEW (Inertia + lifecycle)
  app/Http/Controllers/Export/ExportController.php                      EDIT (abilities, JSON kept)
  app/Http/Resources/Export/DataProcessingJobResource.php               EDIT (metadata/artifacts/can)
  app/Services/DataProcessingJob/DataProcessingJobService.php           EDIT (dispatch/lifecycle/stats/notify)
  app/Services/User/UserService.php                                     EDIT (public invite())
  app/Policies/DataProcessingJobPolicy.php                              EDIT (data-processing.* + entity/type-aware create)
  config/permission-registry.php                                        EDIT (+data-processing, +import.create, reduce export, +user.import/export)
  database/seeders/RoleSeeder.php                                        EDIT (grant DPC + module perms)
  routes/activity.php                                                   NEW
  routes/web.php                                                        EDIT (require activity)
  routes/exports.php                                                    (kept: JSON + download)

Frontend
  resources/js/pages/data-processing/index.tsx                          NEW
  resources/js/components/data-processing/*                              NEW (see §12.4)
  resources/js/hooks/use-job-poll.ts                                     NEW
  resources/js/hooks/use-job-transitions.ts                              NEW
  resources/js/hooks/use-job-queued.tsx                                  NEW
  resources/js/hooks/use-data-table-filters.ts                           (reuse)
  resources/js/lib/schemas/data-processing.ts                            NEW (Zod)
  resources/js/types/data-processing.ts                                  NEW
  resources/js/types/navigation.ts                                       EDIT (NavItem.badge)
  resources/js/types/index.ts                                           EDIT
  resources/js/components/nav-main.tsx                                   EDIT (badge)
  resources/js/components/app-sidebar.tsx                                EDIT (+Activities +badge)
  resources/js/layouts/app-layout.tsx                                    EDIT (mount JobQueuedDialog)
  resources/js/components/notification/notification-bell.tsx             (unchanged; types flow through)
  resources/js/hooks/use-notification-poll.ts                            EDIT (only += activeJobs)

Shared
  app/Http/Middleware/HandleInertiaRequests.php                          EDIT (+activeJobs, +can data-processing)
```

> Run `npm run build` after adding routes so Wayfinder emits `@/routes/activity`.

**Phase 3 — User module integration (module-owned entry points over the same center):**

```text
  app/Exports/UserExport.php                                            registered on DataEntity::USERS (+total())
  app/Imports/UserImport.php                                            registered on DataEntity::USERS
  app/Services/User/UserService.php                                     EDIT (public invite())
  app/Http/Controllers/User/UserController.php                          EDIT (+export/+import → service->dispatch)
  resources/js/pages/users/index.tsx                                    EDIT (Export / Import actions, permission-gated)
  resources/js/components/user/user-processing-menu.tsx                 NEW (entry points; reuses center dialogs/hooks)
  tests/Feature/User/UserProcessingTest.php                             NEW
```

---

## 18. Testing plan (Pest, 1:1 mapping)

### Unit — services
- `DataProcessingJobService`:
  - `dispatch` persists + dispatches the correct job class (fakes `Queue`).
  - `createExport`/`createImport` delegate to `dispatch`.
  - `markProcessing/markProgress/markCompleted` write counts + `stage`.
  - `cancel`/`retry`/`duplicate` produce the right states/rows.
  - `notifyFinished` creates the right `NotificationType` per type+status, targets
    the owner, no-ops when `user_id` is null, and swallows failures.
  - `statsFor` + `activeCountFor` respect ownership / `.view.all`.
- `UserImport` (mocked `UserRepositoryInterface`): creates invited users, skips
  duplicates (DB + in-file), records validation errors, correct `result()`.
- `DataEntity` registry: `makeExporter`/`makeImporter`/`supports` per case.

### Feature — controllers/jobs
- `ActivityController`: index props; storeExport/storeImport authorize + queue;
  cancel/retry/duplicate/destroy; template CSV; forbidden without perms; owners
  scoped.
- `ProcessExportJobTest` (edit): progress counts, notification on success/failure.
- `ProcessImportJobTest` (new): fake CSV on a fake disk → users created, duplicates
  skipped, report stored, completed, notified; invalid file → failed + notified.
- `DataProcessingJobFeatureTest` (edit): new abilities, import assertions.
- `ImportTemplateTest` (new): `GET /activity/import/template/{entity}` returns CSV
  with the entity headings + example row, and XLSX with `?format=xlsx`; forbidden
  without the `create` ability.
- `HandleInertiaRequestsTest` (new): `activeJobs` shared prop gated by permission.

### Feature — permissions (the three tiers)
- `PolicyTest`: `create` allows `user.export` **OR** `export.create`; denys when
  neither; global `export.create` allows any entity; `view.all` cross-user; owner
  `manage`/`delete`; `.manage`/`.delete` for other users' jobs.
- `RoleSeederTest`: Admin/Member receive the §11.4 defaults; a role with only a
  module-level permission can act on that entity and nothing else.

### Feature — User module integration (Phase 3)
- `UserProcessingTest`: the Users page exposes Export/Import only with
  `user.export`/`user.import` (or the global fallback); posting queues a
  `ProcessExport`/`ProcessImport` job scoped to the acting user; the `job_queued`
  flash is set; unauthorized users are forbidden.

### Mock data
- `tests/Mock/ImportMockData.php` (valid + duplicate + invalid CSV rows, requests).
- `tests/Mock/ExportMockData.php` retained.

### Frontend
- No JS runner; verify via Inertia feature assertions + manual review. Optional
  future Vitest harness tracked in `UI_BUILD_PLAN.md`.

---

## 19. Phased implementation checklist

> **Sequencing:** the Data Processing Center is built and proven **first**
> (Phases 0–2). Only then is the **User module** wired in as a consumer
> (Phase 3). This keeps the center generic and stops user-specific assumptions
> leaking into its API.

### Phase 0 — foundations (schema, generic registry, permissions)
- [ ] Migration (name/stage/input_*/cancel_requested_at + `user_id,status` index); model casts/helpers/scopes.
- [ ] `DataEntity` registry replacing `ExportEntity`; `permissionKey()` + template metadata (`importHeadings`/`importSampleRow`/`importColumnHelp`); update cast + references.
- [ ] `DataProcessingJobType` (+REPORT, `label()`/`icon()`), `DataProcessingJobStatus` (+CANCELLED).
- [ ] `NotificationType` additions.
- [ ] **Permissions:** `data-processing.*`, global `import.create`, reduce `export.*`
      to `export.create`, add `user.export`/`user.import`; `php artisan permission:sync`; update `RoleSeeder`.
- [ ] Factory states (`import`/`report`/`active`/`cancelled`).

### Phase 1 — engines, producer API, lifecycle & notifications
- [ ] `Exportable::total()/stage()`; export engine progress/stage/cancel/notify.
- [ ] `Importable`, `ImportResult`, `ImportCounter`, `ProcessImport` engine + `ImportTemplateExport` (tested with a fixture importer).
- [ ] `JobRequest` DTO + `DataProcessingJobService::dispatch` + `DataProcessingJobDispatcher`.
- [ ] `markProcessing/markProgress/markCompleted` (counts/stage); `cancel`/`retry`/`duplicate`; `statsFor`/`activeCountFor`.
- [ ] `notifyFinished` + `NotificationType::forJob`.
- [ ] Enrich `DataProcessingJobResource` (metadata/artifacts/can).
- [ ] Policy rewrite (§11.3) + unit/feature tests for the engines, dispatch, lifecycle and policy tiers.

### Phase 2 — Data Processing Center UI (generic)
- [ ] `routes/activity.php`, `ActivityController`, `web.php`.
- [ ] Types, Zod schema, `use-job-poll`/`use-job-transitions`/`use-job-queued`.
- [ ] All `components/data-processing/*`; `pages/data-processing/index.tsx`.
- [ ] Export/Import dialogs (entity-picker driven, **downloadable template picker**); day grouping; stat cards; detail drawer; ETA; Live/Paused.
- [ ] Sidebar Activities item + badge; `HandleInertiaRequests` `activeJobs` + `can`; poll `only`.
- [ ] Global `JobQueuedDialog` in the app layout.
- [ ] Files → Reports: completed-only + "Open in Activities".
- [ ] `npm run build` (Wayfinder) + manual polish pass.

### Phase 3 — User module integration (first real consumer)
- [ ] Register `DataEntity::USERS` with `UserExport` (exists, +`total()`) and `UserImport` (+`UserImportReportExport`).
- [ ] `UserService::invite()` extraction; `UserImport` uses it.
- [ ] `UserController::export` / `::import` actions that `Gate::authorize('create', [...])`
      and call `service->dispatch(...)`; reuse `StoreExportRequest`/`StoreImportRequest`.
- [ ] Users page: **Export** / **Import** actions gated by `user.export` / `user.import`
      (or the global fallback), reusing the center's dialogs/hooks and the global
      `JobQueuedDialog`.
- [ ] Verify module-level vs global authorization (`user.export` alone acts only on users).
- [ ] Tests: `UserProcessingTest`, `UserImport` unit tests, `ImportMockData`.

### Phase 4 — hardening & polish
- [ ] `composer check` green (Pint, PHPStan, Pest, frontend lint, types).
- [ ] Verify on Windows with `php artisan queue:work --queue=critical,default,heavy`.
- [ ] Accessibility pass (roles, aria-live, focus order, contrast, reduced-motion).
- [ ] Every state designed: empty/skeleton/error/paused/offline.
- [ ] Large-file smoke test (10k-row CSV) for chunking, progress, ETA, cancel.
- [ ] Update `AGENTS.md` §7.14 (Job Center + producer API + permission tiers) and close `todo.md`.

### Phase 5 — future consumers (not this plan's build)
- [ ] Report generation (`DataEntity` cases + report exporters on the `report` type).
- [ ] Surface Clockify sync runs through the same center.

---

## 20. Risks & mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| `maatwebsite/excel` 4.0 import API differs from 3.x docs | Job fails | Prototype `Excel::import` with `ToCollection`/`WithHeadingRow`/`WithChunkReading` in Phase 2; fall back to `PhpSpreadsheet` streaming if a concern is absent. |
| Cancelling a processing job mid-flight | Orphan work | Cooperative flag checked at chunk/stage boundaries; UI labels it "Stop", not instant. |
| Large imports send many invitation emails | Mail/queue flood | `send_invitations` option + queued mail + docs; admins can batch/resend. |
| Determinate progress for huge XLSX | Slow/incorrect | Cheap `getHighestRow()`/line count; indeterminate bar fallback. |
| Polling load | DB/CPU | Active-only polling, 3s, visibility-aware, partial reloads, user "Live/Paused". |
| `ExportEntity` → `DataEntity` rename | Test churn | Values unchanged (no data migration); update references + tests in one PR. |
| Retiring `export.view`/`export.view.all`/`export.delete` | Roles/tests reference them | `permission:sync` prunes them; `RoleSeeder` re-grants `data-processing.*`; update `ExportController`, `FileController`, policy + tests in one PR. |
| Module-level vs global permission confusion | Over/under-granting | One resolver (`DataEntity::permissionKey()`) + documented matrix (§11.2) + policy tests covering both paths. |
| Duplicate/retry of an import whose file expired | Broken job | Disable "Run again" when `input_path` no longer exists; surface a hint. |
| Notification now a service dependency | DI cycle | Dependency is one-way (Notification does not know jobs); constructed via container. |

---

## 21. Deferred / follow-ups

- Report **content** exporters (`ReportExport` for TimeEntries/Projects) on the
  `report` operation type — the reason this center exists, built next.
- **Repeat the Phase-3 pattern per module** as data lands: add `{module}.export` /
  `{module}.import` permissions, a `DataEntity` case and module entry points — the
  center itself needs no changes (this is the whole point).
- Multiple artifacts per job (`data_processing_job_artifacts` table).
- Bulk selection + bulk actions (download all, retry failed, delete).
- Saved/favourite jobs & scheduled jobs (cron-like producers).
- Real-time upgrade: SSE/websocket delivery if the notification SSE design is
  revisited (the poll hook is intentionally swappable).
- Column-mapping import UI and multi-sheet imports.
- Import checkpoint/resume (TDR §23–24 style).
- Clockify sync runs surfaced through the same Job Center.
- PDF export.
- Rename `filters` → `parameters` column (non-breaking).
