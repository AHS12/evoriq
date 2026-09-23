<?php

namespace App\Services\DataProcessingJob;

use App\DTOs\DataProcessingJob\DataProcessingJobDTO;
use App\DTOs\DataProcessingJob\DataProcessingJobFilterDTO;
use App\Enums\DataProcessingJobStatus;
use App\Jobs\ProcessExport;
use App\Models\DataProcessingJob;
use App\Repositories\Contracts\DataProcessingJobRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataProcessingJobService
{
    public function __construct(
        protected DataProcessingJobRepositoryInterface $repository,
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
     * Create an export job and dispatch it to the queue.
     */
    public function createExport(DataProcessingJobDTO $dto): DataProcessingJob
    {
        $job = DB::transaction(function () use ($dto): DataProcessingJob {
            $attributes = $dto->toArray();
            $attributes['user_id'] ??= auth()->id();

            return $this->repository->create($attributes);
        });

        ProcessExport::dispatch($job);

        return $job;
    }

    /**
     * Mark a job as processing.
     */
    public function markProcessing(DataProcessingJob $job): DataProcessingJob
    {
        return $this->repository->update($job, [
            'status' => DataProcessingJobStatus::PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Attach the generated file to the job.
     */
    public function attachFile(
        DataProcessingJob $job,
        string $fileName,
        string $filePath,
        string $fileDisk,
    ): DataProcessingJob {
        return $this->repository->update($job, [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_disk' => $fileDisk,
        ]);
    }

    /**
     * Mark a job as completed with its result counts.
     */
    public function markCompleted(
        DataProcessingJob $job,
        int $totalItems = 0,
        int $processedItems = 0,
        int $errorCount = 0,
    ): DataProcessingJob {
        return $this->repository->update($job, [
            'status' => DataProcessingJobStatus::COMPLETED,
            'total_items' => $totalItems,
            'processed_items' => $processedItems,
            'success_count' => max(0, $processedItems - $errorCount),
            'error_count' => $errorCount,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark a job as failed and record the error.
     */
    public function markFailed(DataProcessingJob $job, string $errorMessage): DataProcessingJob
    {
        return $this->repository->update($job, [
            'status' => DataProcessingJobStatus::FAILED,
            'error_message' => $errorMessage,
            'errors' => [[
                'type' => 'export_error',
                'message' => $errorMessage,
                'timestamp' => now()->toDateTimeString(),
            ]],
            'completed_at' => now(),
        ]);
    }

    /**
     * Delete a job and its generated file.
     */
    public function delete(DataProcessingJob $job): bool
    {
        return DB::transaction(function () use ($job): bool {
            $this->deleteFile($job);

            return $this->repository->delete($job);
        });
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

    public function countCompletedBefore(Carbon $cutoff): int
    {
        return $this->repository->countCompletedBefore($cutoff);
    }

    /**
     * @return Collection<int, DataProcessingJob>
     */
    public function completedBefore(Carbon $cutoff, int $limit = 0): Collection
    {
        return $this->repository->completedBefore($cutoff, $limit);
    }

    private function deleteFile(DataProcessingJob $job): void
    {
        if ($job->file_path === null) {
            return;
        }

        foreach ($this->candidateDisks($job) as $disk) {
            if (Storage::disk($disk)->exists($job->file_path)) {
                Storage::disk($disk)->delete($job->file_path);
            }
        }
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
