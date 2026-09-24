<?php

namespace App\Models;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportFormat;
use Database\Factories\DataProcessingJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $job_id
 * @property DataProcessingJobType $type
 * @property string|null $name
 * @property DataProcessingJobStatus $status
 * @property string|null $stage
 * @property DataEntity|null $entity_type
 * @property ExportFormat|null $format
 * @property array<string, mixed>|null $filters
 * @property string|null $input_disk
 * @property string|null $input_path
 * @property int|null $input_size
 * @property string|null $input_mime_type
 * @property string|null $file_name
 * @property string|null $file_disk
 * @property string|null $file_path
 * @property string|null $original_file_name
 * @property int|null $file_size
 * @property string|null $mime_type
 * @property int|null $total_items
 * @property int|null $processed_items
 * @property int|null $success_count
 * @property int|null $error_count
 * @property array<int, mixed>|null $errors
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancel_requested_at
 * @property int|null $user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'job_id', 'type', 'name', 'status', 'stage', 'entity_type', 'format', 'filters',
    'input_disk', 'input_path', 'input_size', 'input_mime_type',
    'file_name', 'file_disk', 'file_path', 'original_file_name', 'file_size', 'mime_type',
    'total_items', 'processed_items', 'success_count', 'error_count', 'errors', 'error_message',
    'started_at', 'completed_at', 'cancel_requested_at', 'user_id', 'created_by', 'updated_by',
])]
class DataProcessingJob extends Model
{
    /** @use HasFactory<DataProcessingJobFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DataProcessingJobType::class,
            'status' => DataProcessingJobStatus::class,
            'entity_type' => DataEntity::class,
            'format' => ExportFormat::class,
            'filters' => 'array',
            'errors' => 'array',
            'file_size' => 'integer',
            'input_size' => 'integer',
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'success_count' => 'integer',
            'error_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
        ];
    }

    /**
     * The user the job belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user that created the job.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user that last updated the job.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DataProcessingJobStatus::PENDING);
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', DataProcessingJobStatus::PROCESSING);
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', DataProcessingJobStatus::COMPLETED);
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', DataProcessingJobStatus::FAILED);
    }

    /**
     * Jobs that are still queued or running.
     *
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            DataProcessingJobStatus::PENDING,
            DataProcessingJobStatus::PROCESSING,
        ]);
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeImports(Builder $query): Builder
    {
        return $query->where('type', DataProcessingJobType::IMPORT);
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeExports(Builder $query): Builder
    {
        return $query->where('type', DataProcessingJobType::EXPORT);
    }

    /**
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeReports(Builder $query): Builder
    {
        return $query->where('type', DataProcessingJobType::REPORT);
    }

    public function isImport(): bool
    {
        return $this->type === DataProcessingJobType::IMPORT;
    }

    public function isExport(): bool
    {
        return $this->type === DataProcessingJobType::EXPORT;
    }

    public function isReport(): bool
    {
        return $this->type === DataProcessingJobType::REPORT;
    }

    public function isCompleted(): bool
    {
        return $this->status === DataProcessingJobStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === DataProcessingJobStatus::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === DataProcessingJobStatus::CANCELLED;
    }

    /**
     * Whether the job is still queued or running.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether cancellation has been requested but not yet applied.
     */
    public function cancellationRequested(): bool
    {
        return $this->cancel_requested_at !== null;
    }

    /**
     * The completion percentage of the job (0–100).
     */
    public function progressPercentage(): int
    {
        if (! $this->total_items) {
            return 0;
        }

        return (int) min(100, (($this->processed_items ?? 0) / $this->total_items) * 100);
    }

    /**
     * Whether the job has a downloadable artifact.
     */
    public function isDownloadable(): bool
    {
        return $this->isCompleted() && $this->file_path !== null;
    }

    /**
     * The downloadable artifacts for this job (forward-compatible list).
     *
     * @return array<int, array<string, mixed>>
     */
    public function artifactList(): array
    {
        if ($this->file_path === null) {
            return [];
        }

        $cleanupDays = (int) config('exports.cleanup_days', 7);

        return [[
            'key' => 'primary',
            'label' => $this->isImport() ? 'Import report' : 'Generated file',
            'file_name' => $this->file_name,
            'size' => $this->file_size,
            'mime_type' => $this->mime_type,
            'downloadable' => $this->isDownloadable(),
            'expires_at' => $this->completed_at?->copy()->addDays($cleanupDays)->toIso8601String(),
        ]];
    }

    /**
     * A human title for the job: the producer-supplied name, or a derived
     * "{Entity} {operation}" label.
     */
    public function displayName(): string
    {
        if (is_string($this->name) && $this->name !== '') {
            return $this->name;
        }

        $entity = $this->entity_type?->label() ?? 'Data';

        return trim($entity.' '.$this->type->label());
    }

    /**
     * A short, human duration (e.g. "2.4s", "1m 12s", "3h 5m").
     */
    public function durationLabel(): ?string
    {
        if ($this->started_at === null) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $seconds = max(0, (int) $this->started_at->diffInSeconds($end));

        if ($seconds < 60) {
            return $seconds.'s';
        }

        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return $remainingSeconds > 0 ? "{$minutes}m {$remainingSeconds}s" : "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes > 0 ? "{$hours}h {$remainingMinutes}m" : "{$hours}h";
    }
}
