<?php

namespace App\Models;

use App\Contracts\PipelineRunnable;
use App\Enums\PipelineRunType;
use App\Enums\SyncMode;
use App\Enums\SyncPriority;
use App\Enums\SyncRunStatus;
use App\Enums\SyncTrigger;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifySyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single synchronization run: the umbrella that groups every job needed to
 * pull a workspace over a range (SYNC-01, SYNC-09). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property int|null $workspace_id
 * @property SyncTrigger $trigger
 * @property SyncMode $mode
 * @property SyncPriority $priority
 * @property SyncRunStatus $status
 * @property Carbon|null $range_start
 * @property Carbon|null $range_end
 * @property array<string, mixed>|null $plan
 * @property int $total_jobs
 * @property int $completed_jobs
 * @property int $records_created
 * @property int $records_updated
 * @property int $records_deleted
 * @property int $api_requests_used
 * @property string|null $error_message
 * @property string|null $correlation_id
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'connection_id', 'workspace_id', 'trigger', 'mode',
    'priority', 'status', 'range_start', 'range_end', 'plan', 'total_jobs',
    'completed_jobs', 'records_created', 'records_updated', 'records_deleted',
    'api_requests_used', 'error_message', 'correlation_id', 'started_at',
    'completed_at',
])]
class ClockifySyncRun extends Model implements PipelineRunnable
{
    /**
     * @use HasFactory<ClockifySyncRunFactory>
     * @use BelongsToOrganization<ClockifySyncRun>
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
            'trigger' => SyncTrigger::class,
            'mode' => SyncMode::class,
            'priority' => SyncPriority::class,
            'status' => SyncRunStatus::class,
            'range_start' => 'datetime',
            'range_end' => 'datetime',
            'plan' => 'array',
            'total_jobs' => 'integer',
            'completed_jobs' => 'integer',
            'records_created' => 'integer',
            'records_updated' => 'integer',
            'records_deleted' => 'integer',
            'api_requests_used' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The connection the run pulls from.
     *
     * @return BelongsTo<ClockifyConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ClockifyConnection::class);
    }

    /**
     * The workspace the run is scoped to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }

    /**
     * The jobs that make up the run.
     *
     * @return HasMany<ClockifySyncJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(ClockifySyncJob::class, 'sync_run_id');
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Sync runs write into the shared pipeline event stream (SYNC-14).
     */
    public function pipelineRunType(): PipelineRunType
    {
        return PipelineRunType::SYNC;
    }

    public function pipelineRunId(): string
    {
        return (string) $this->getKey();
    }
}
