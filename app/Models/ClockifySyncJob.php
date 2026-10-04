<?php

namespace App\Models;

use App\Enums\SyncEntityType;
use App\Enums\SyncJobStatus;
use App\Enums\SyncPhase;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifySyncJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A resumable unit of work inside a sync run: one entity over one range,
 * checkpointed by page (SYNC-01, SYNC-04). Organization-owned.
 *
 * @property int $id
 * @property int $sync_run_id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property SyncEntityType $entity_type
 * @property SyncPhase $phase
 * @property string|null $user_clockify_id
 * @property Carbon|null $range_start
 * @property Carbon|null $range_end
 * @property int $page
 * @property int $page_size
 * @property int $records_processed
 * @property int $records_created
 * @property int $records_updated
 * @property int $records_deleted
 * @property SyncJobStatus $status
 * @property int $attempt
 * @property array<string, mixed>|null $checkpoint
 * @property string|null $last_error
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $heartbeat_at
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'sync_run_id', 'organization_id', 'workspace_id', 'entity_type', 'phase',
    'user_clockify_id', 'range_start', 'range_end', 'page', 'page_size', 'records_processed',
    'records_created', 'records_updated', 'records_deleted', 'status', 'attempt',
    'checkpoint', 'last_error', 'started_at', 'completed_at', 'heartbeat_at',
    'next_retry_at',
])]
class ClockifySyncJob extends Model
{
    /**
     * @use HasFactory<ClockifySyncJobFactory>
     * @use BelongsToOrganization<ClockifySyncJob>
     */
    use BelongsToOrganization, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => SyncEntityType::class,
            'phase' => SyncPhase::class,
            'status' => SyncJobStatus::class,
            'range_start' => 'datetime',
            'range_end' => 'datetime',
            'page' => 'integer',
            'page_size' => 'integer',
            'records_processed' => 'integer',
            'records_created' => 'integer',
            'records_updated' => 'integer',
            'records_deleted' => 'integer',
            'attempt' => 'integer',
            'checkpoint' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'heartbeat_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    /**
     * The run this job belongs to.
     *
     * @return BelongsTo<ClockifySyncRun, $this>
     */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(ClockifySyncRun::class, 'sync_run_id');
    }

    /**
     * The workspace this job is scoped to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }
}
