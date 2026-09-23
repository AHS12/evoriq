<?php

namespace App\Models;

use App\Enums\DataProcessingJobStatus;
use App\Enums\DataProcessingJobType;
use App\Enums\ExportEntity;
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
 * @property DataProcessingJobStatus $status
 * @property ExportEntity|null $entity_type
 * @property ExportFormat|null $format
 * @property array<string, mixed>|null $filters
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
 * @property int|null $user_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
#[Fillable([
    'job_id', 'type', 'status', 'entity_type', 'format', 'filters',
    'file_name', 'file_disk', 'file_path', 'original_file_name', 'file_size', 'mime_type',
    'total_items', 'processed_items', 'success_count', 'error_count', 'errors', 'error_message',
    'started_at', 'completed_at', 'user_id', 'created_by', 'updated_by',
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
            'entity_type' => ExportEntity::class,
            'format' => ExportFormat::class,
            'filters' => 'array',
            'errors' => 'array',
            'file_size' => 'integer',
            'total_items' => 'integer',
            'processed_items' => 'integer',
            'success_count' => 'integer',
            'error_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @param  Builder<DataProcessingJob>  $query
     * @return Builder<DataProcessingJob>
     */
    public function scopeExports(Builder $query): Builder
    {
        return $query->where('type', DataProcessingJobType::EXPORT);
    }

    public function isCompleted(): bool
    {
        return $this->status === DataProcessingJobStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === DataProcessingJobStatus::FAILED;
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
}
