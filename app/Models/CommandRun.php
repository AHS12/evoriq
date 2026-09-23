<?php

namespace App\Models;

use App\Enums\CommandRunStatus;
use Database\Factories\CommandRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $action
 * @property string $command
 * @property CommandRunStatus $status
 * @property int $progress
 * @property string|null $output
 * @property int|null $exit_code
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
    'action', 'command', 'status', 'progress', 'output', 'exit_code', 'error_message',
    'started_at', 'completed_at', 'user_id', 'created_by', 'updated_by',
])]
class CommandRun extends Model
{
    /** @use HasFactory<CommandRunFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommandRunStatus::class,
            'progress' => 'integer',
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The user that queued the run.
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
     * @param  Builder<CommandRun>  $query
     * @return Builder<CommandRun>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', CommandRunStatus::PENDING);
    }

    /**
     * @param  Builder<CommandRun>  $query
     * @return Builder<CommandRun>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', CommandRunStatus::RUNNING);
    }

    /**
     * @param  Builder<CommandRun>  $query
     * @return Builder<CommandRun>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', CommandRunStatus::COMPLETED);
    }

    /**
     * @param  Builder<CommandRun>  $query
     * @return Builder<CommandRun>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', CommandRunStatus::FAILED);
    }

    /**
     * Whether the run has finished (completed or failed).
     */
    public function isFinished(): bool
    {
        return $this->status->isFinal();
    }
}
