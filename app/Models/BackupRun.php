<?php

namespace App\Models;

use App\Enums\BackupRunStatus;
use App\Enums\BackupRunTrigger;
use App\Enums\BackupRunType;
use Database\Factories\BackupRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $job_id
 * @property BackupRunType $type
 * @property BackupRunStatus $status
 * @property BackupRunTrigger $trigger
 * @property string|null $disk
 * @property string|null $file_name
 * @property int|null $file_size
 * @property int|null $exit_code
 * @property string|null $error_message
 * @property string|null $output
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
    'job_id', 'type', 'status', 'trigger', 'disk', 'file_name', 'file_size', 'exit_code',
    'error_message', 'output', 'started_at', 'completed_at', 'user_id', 'created_by', 'updated_by',
])]
class BackupRun extends Model
{
    /** @use HasFactory<BackupRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BackupRunType::class,
            'status' => BackupRunStatus::class,
            'trigger' => BackupRunTrigger::class,
            'file_size' => 'integer',
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The user that triggered a manual run.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The user that created the run.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user that last updated the run.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', BackupRunStatus::PENDING);
    }

    /**
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', BackupRunStatus::RUNNING);
    }

    /**
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', BackupRunStatus::COMPLETED);
    }

    /**
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', BackupRunStatus::FAILED);
    }

    /**
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopeBackups(Builder $query): Builder
    {
        return $query->where('type', BackupRunType::BACKUP);
    }

    /**
     * Whether the run has finished (completed or failed).
     */
    public function isFinished(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Whether a backup archive can be downloaded from this run.
     */
    public function isDownloadable(): bool
    {
        return $this->type === BackupRunType::BACKUP
            && $this->status === BackupRunStatus::COMPLETED
            && $this->file_name !== null
            && $this->disk !== null;
    }
}
