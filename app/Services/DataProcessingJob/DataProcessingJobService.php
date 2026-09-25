<?php

namespace App\Services\DataProcessingJob;

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\DTOs\DataProcessingJob\JobRequest;
use App\DTOs\Notification\NotificationDTO;
use App\DTOs\Notification\NotificationTargetDTO;
use App\Enums\AuditEvent;
use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\ExportFormat;
use App\Enums\NotificationTargetType;
use App\Enums\NotificationType;
use App\Exports\ImportReportExport;
use App\Imports\ImportResult;
use App\Jobs\DataProcessingJobDispatcher;
use App\Models\DataProcessingJob;
use App\Models\User;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;

class DataProcessingJobService
{
    public function __construct(
        protected DataProcessingJobRepositoryInterface $repository,
        protected NotificationService $notifications,
        protected AuditLogService $audit,
    ) {}

    /**
     * @return LengthAwarePaginator<int, DataProcessingJob>
     */
    public function paginate(DataProcessingJobFilterDTO $filters): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $filters->perPage);
    }

    public function find(int $id): ?DataProcessingJob
    {
        return $this->repository->findById($id);
    }

    public function findByJobId(string $jobId): ?DataProcessingJob
    {
        return $this->repository->findByJobId($jobId);
    }

    /**
     * Queue a job from a producer request and dispatch it to the queue.
     */
    public function dispatch(JobRequest $request): DataProcessingJob
    {
        $attributes = array_filter([
            'type' => $request->type->value,
            'entity_type' => $request->entity->value,
            'format' => $request->format?->value,
            'name' => $request->name,
            'filters' => $request->parameters,
            'user_id' => $request->userId ?? auth()->id(),
            'input_disk' => $request->inputDisk,
            'input_path' => $request->inputPath,
            'original_file_name' => $request->originalFileName,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->createAndDispatch($attributes);
    }

    /**
     * Create an export job and dispatch it to the queue.
     */
    public function createExport(DataProcessingJobDTO $dto): DataProcessingJob
    {
        return $this->createAndDispatch($dto->toArray());
    }

    /**
     * Persist the job inside a transaction, then dispatch after it commits.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createAndDispatch(array $attributes): DataProcessingJob
    {
        $attributes['user_id'] ??= auth()->id();

        $job = DB::transaction(fn (): DataProcessingJob => $this->repository->create($attributes));

        DataProcessingJobDispatcher::dispatch($job);

        return $job;
    }

    /**
     * Mark a job as processing and record its total item count and stage.
     */
    public function markProcessing(DataProcessingJob $job, ?int $totalItems = null, ?string $stage = null): DataProcessingJob
    {
        $data = [
            'status' => DataProcessingJobStatus::PROCESSING,
            'stage' => $stage,
        ];

        if ($job->status !== DataProcessingJobStatus::PROCESSING) {
            $data['started_at'] = now();
        }

        if ($totalItems !== null) {
            $data['total_items'] = $totalItems;
            $data['processed_items'] = 0;
        }

        return $this->repository->update($job, $data);
    }

    /**
     * Record incremental progress for a running job.
     */
    public function markProgress(DataProcessingJob $job, int $processedItems, ?string $stage = null): DataProcessingJob
    {
        $data = ['processed_items' => $processedItems];

        if ($stage !== null) {
            $data['stage'] = $stage;
        }

        return $this->repository->update($job, $data);
    }

    /**
     * Attach the generated artifact to the job.
     */
    public function attachArtifact(
        DataProcessingJob $job,
        string $fileName,
        string $filePath,
        string $fileDisk,
        ?int $fileSize = null,
        ?string $mimeType = null,
    ): DataProcessingJob {
        return $this->repository->update($job, [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_disk' => $fileDisk,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
        ]);
    }

    /**
     * Mark a job as completed with its result counts.
     *
     * @param  array<int, mixed>|null  $errors
     */
    public function markCompleted(
        DataProcessingJob $job,
        int $totalItems = 0,
        int $processedItems = 0,
        ?int $successCount = null,
        int $errorCount = 0,
        ?array $errors = null,
    ): DataProcessingJob {
        $job = $this->repository->update($job, [
            'status' => DataProcessingJobStatus::COMPLETED,
            'stage' => null,
            'total_items' => $totalItems,
            'processed_items' => $processedItems,
            'success_count' => $successCount ?? max(0, $processedItems - $errorCount),
            'error_count' => $errorCount,
            'errors' => $errors,
            'completed_at' => now(),
        ]);

        if (! $job->isReport()) {
            $event = $job->isImport() ? AuditEvent::IMPORT_COMPLETED : AuditEvent::EXPORT_COMPLETED;

            $this->audit->record(
                $event,
                $job,
                ['entity' => $job->entity_type?->value, 'file_name' => $job->file_name, 'rows' => $processedItems],
                actor: User::find($job->user_id),
                description: __(':entity :operation completed', [
                    'entity' => $job->entity_type?->label() ?? 'Data',
                    'operation' => strtolower($job->type->label()),
                ]),
            );
        }

        return $job;
    }

    /**
     * Mark a job as failed and record the error.
     */
    public function markFailed(DataProcessingJob $job, string $errorMessage): DataProcessingJob
    {
        $job = $this->repository->update($job, [
            'status' => DataProcessingJobStatus::FAILED,
            'stage' => null,
            'error_message' => $errorMessage,
            'errors' => [[
                'row' => 0,
                'type' => 'error',
                'message' => $errorMessage,
            ]],
            'completed_at' => now(),
        ]);

        if (! $job->isReport()) {
            $event = $job->isImport() ? AuditEvent::IMPORT_FAILED : AuditEvent::EXPORT_FAILED;

            $this->audit->record(
                $event,
                $job,
                ['entity' => $job->entity_type?->value, 'error' => $errorMessage],
                actor: User::find($job->user_id),
                description: __(':entity :operation failed', [
                    'entity' => $job->entity_type?->label() ?? 'Data',
                    'operation' => strtolower($job->type->label()),
                ]),
            );
        }

        return $job;
    }

    /**
     * Mark a job as cancelled.
     */
    public function markCancelled(DataProcessingJob $job): DataProcessingJob
    {
        $job = $this->repository->update($job, [
            'status' => DataProcessingJobStatus::CANCELLED,
            'stage' => null,
            'completed_at' => now(),
        ]);

        $this->audit->record(
            AuditEvent::PROCESSING_CANCELLED,
            $job,
            ['entity' => $job->entity_type?->value],
            description: __(':entity :operation cancelled', [
                'entity' => $job->entity_type?->label() ?? 'Data',
                'operation' => strtolower($job->type->label()),
            ]),
        );

        return $job;
    }

    /**
     * Cancel a job: queued jobs stop immediately, running jobs are asked to
     * stop at the next safe boundary.
     */
    public function cancel(DataProcessingJob $job): DataProcessingJob
    {
        if ($job->status->isFinal()) {
            return $job;
        }

        if ($job->status === DataProcessingJobStatus::PENDING) {
            return $this->markCancelled($job);
        }

        $updated = $this->repository->update($job, ['cancel_requested_at' => now()]);

        $this->audit->record(
            AuditEvent::PROCESSING_CANCELLED,
            $updated,
            ['entity' => $updated->entity_type?->value, 'requested' => true],
            actor: auth()->user(),
            description: __(':entity :operation cancellation requested', [
                'entity' => $updated->entity_type?->label() ?? 'Data',
                'operation' => strtolower($updated->type->label()),
            ]),
        );

        return $updated;
    }

    /**
     * Re-queue a finished job with a clean slate.
     */
    public function retry(DataProcessingJob $job): DataProcessingJob
    {
        if (! $job->status->isFinal()) {
            throw new RuntimeException(__('Only finished jobs can be retried.'));
        }

        $job = $this->repository->update($job, [
            'status' => DataProcessingJobStatus::PENDING,
            'stage' => null,
            'processed_items' => 0,
            'success_count' => null,
            'error_count' => null,
            'errors' => null,
            'error_message' => null,
            'started_at' => null,
            'completed_at' => null,
            'cancel_requested_at' => null,
        ]);

        DataProcessingJobDispatcher::dispatch($job);

        return $job;
    }

    /**
     * Queue a fresh copy of a job with the same parameters.
     */
    public function duplicate(DataProcessingJob $job): DataProcessingJob
    {
        if ($job->entity_type === null) {
            throw new RuntimeException(__('This job has no entity and cannot be duplicated.'));
        }

        if ($job->isImport() && ($job->input_path === null || ! Storage::disk($this->disk($job->input_disk))->exists($job->input_path))) {
            throw new RuntimeException(__('The source file for this import is no longer available.'));
        }

        $userId = auth()->id();

        return $this->dispatch(new JobRequest(
            entity: $job->entity_type,
            type: $job->type,
            format: $job->format,
            name: $job->name,
            parameters: $job->filters ?? [],
            userId: $userId !== null ? (int) $userId : $job->user_id,
            inputDisk: $job->input_disk,
            inputPath: $job->input_path,
            originalFileName: $job->original_file_name,
        ));
    }

    /**
     * Write the skipped/failed rows of an import to a downloadable CSV artifact.
     */
    public function storeImportReport(DataProcessingJob $job, ImportResult $result): ?DataProcessingJob
    {
        if (! $result->hasIssues()) {
            return null;
        }

        $format = ExportFormat::CSV;
        $entityValue = $job->entity_type instanceof DataEntity ? $job->entity_type->value : 'data';
        $fileName = sprintf(
            '%s_import_report_%s.%s',
            $entityValue,
            $job->job_id,
            $format->extension(),
        );
        $basePath = trim((string) config('exports.base_path', 'exports'), '/');
        $filePath = "{$basePath}/imports/{$fileName}";
        $disk = $this->disk();

        Excel::store(new ImportReportExport($result->errors), $filePath, $disk);

        $size = Storage::disk($disk)->exists($filePath) ? Storage::disk($disk)->size($filePath) : null;

        return $this->attachArtifact($job, $fileName, $filePath, $disk, $size, $format->mimeType());
    }

    /**
     * Delete a job and its input/output files.
     */
    public function delete(DataProcessingJob $job): bool
    {
        return DB::transaction(function () use ($job): bool {
            $this->deleteFile($job);

            return $this->repository->delete($job);
        });
    }

    /**
     * @return array<string, int>
     */
    public function statsFor(?int $userId, bool $viewAll): array
    {
        return $this->repository->statusCounts($viewAll ? null : $userId);
    }

    public function activeCountFor(?int $userId, bool $viewAll): int
    {
        return $this->repository->activeCount($viewAll ? null : $userId);
    }

    /**
     * Create an in-app notification for a finished job (owner only).
     */
    public function notifyFinished(DataProcessingJob $job): void
    {
        if ($job->user_id === null) {
            return;
        }

        $type = NotificationType::forJob($job->type, $job->status);
        $entity = $job->entity_type?->label() ?? 'Data';
        $operation = strtolower($job->type->label());
        $count = $job->processed_items ?? 0;

        $title = match (true) {
            $job->isCompleted() => __(':entity :operation ready', ['entity' => $entity, 'operation' => $operation]),
            $job->isCancelled() => __(':entity :operation cancelled', ['entity' => $entity, 'operation' => $operation]),
            default => __(':entity :operation failed', ['entity' => $entity, 'operation' => $operation]),
        };

        $body = match (true) {
            $job->isCompleted() && $job->isDownloadable() => __(':count rows ready. Download your file.', ['count' => $count]),
            $job->isCompleted() => __('Finished processing :count rows.', ['count' => $count]),
            $job->isCancelled() => __('This job was cancelled.'),
            default => $job->error_message ?? __('Something went wrong. Open the job for details.'),
        };

        $actionUrl = $job->isDownloadable()
            ? route('exports.download', $job)
            : (Route::has('activity.index') ? route('activity.index', ['job_id' => $job->job_id]) : null);

        $this->notifications->create(new NotificationDTO(
            type: $type,
            title: $title,
            body: $body,
            actionUrl: $actionUrl,
            data: [
                'job_id' => $job->job_id,
                'type' => $job->type->value,
                'downloadable' => $job->isDownloadable(),
            ],
            targets: [new NotificationTargetDTO(NotificationTargetType::USER, $job->user_id)],
            createdBy: $job->user_id,
        ), $job->user_id);
    }

    public function countCompletedBefore(Carbon $cutoff): int
    {
        return $this->repository->countCompletedBefore($cutoff);
    }

    /**
     * Processing jobs that started before the cutoff (likely stalled).
     *
     * @return Collection<int, DataProcessingJob>
     */
    public function staleProcessingBefore(CarbonInterface $cutoff): Collection
    {
        return $this->repository->staleProcessingBefore($cutoff);
    }

    /**
     * @return Collection<int, DataProcessingJob>
     */
    public function completedBefore(Carbon $cutoff, int $limit = 0): Collection
    {
        return $this->repository->completedBefore($cutoff, $limit);
    }

    /**
     * Resolve the disk, path and download filename for a completed job.
     *
     * @return array{disk: string, path: string, download_name: string}|null
     */
    public function resolveDownload(DataProcessingJob $job): ?array
    {
        if (! $job->isDownloadable() || $job->file_path === null) {
            return null;
        }

        foreach ($this->candidateDisks($job) as $disk) {
            if (Storage::disk($disk)->exists($job->file_path)) {
                return [
                    'disk' => $disk,
                    'path' => $job->file_path,
                    'download_name' => $job->file_name ?? $job->original_file_name ?? basename($job->file_path),
                ];
            }
        }

        return null;
    }

    private function deleteFile(DataProcessingJob $job): void
    {
        $paths = [
            'output' => $job->file_path,
            'input' => $job->input_path,
        ];

        foreach ($paths as $path) {
            if ($path === null) {
                continue;
            }

            foreach ($this->candidateDisks($job) as $disk) {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            }
        }
    }

    private function disk(?string $disk = null): string
    {
        if (is_string($disk) && $disk !== '') {
            return $disk;
        }

        return (string) config('exports.disk', 'local');
    }

    /**
     * @return list<string>
     */
    private function candidateDisks(DataProcessingJob $job): array
    {
        $configured = config('exports.disk');

        $candidates = [$job->file_disk, is_string($configured) ? $configured : null, 'local'];

        $disks = [];

        foreach ($candidates as $disk) {
            if (is_string($disk) && $disk !== '' && ! in_array($disk, $disks, true)) {
                $disks[] = $disk;
            }
        }

        return $disks;
    }
}
