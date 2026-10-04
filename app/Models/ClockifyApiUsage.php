<?php

namespace App\Models;

use App\Enums\ApiUsageWindowType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyApiUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A durable, self-accounted API budget window (SYNC-01, SYNC-02). One row per
 * connection/workspace/window; Clockify exposes no rate-limit headers, so this
 * table is the source of truth. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property int|null $workspace_id
 * @property ApiUsageWindowType $window_type
 * @property Carbon $window_started_at
 * @property Carbon $window_ends_at
 * @property int $requests_used
 * @property int|null $requests_remaining
 * @property int $limit_requests
 * @property Carbon|null $last_request_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'connection_id', 'workspace_id', 'window_type',
    'window_started_at', 'window_ends_at', 'requests_used',
    'requests_remaining', 'limit_requests', 'last_request_at',
])]
class ClockifyApiUsage extends Model
{
    /**
     * @use HasFactory<ClockifyApiUsageFactory>
     * @use BelongsToOrganization<ClockifyApiUsage>
     */
    use BelongsToOrganization, HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clockify_api_usage';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_type' => ApiUsageWindowType::class,
            'window_started_at' => 'datetime',
            'window_ends_at' => 'datetime',
            'requests_used' => 'integer',
            'requests_remaining' => 'integer',
            'limit_requests' => 'integer',
            'last_request_at' => 'datetime',
        ];
    }

    /**
     * The connection the budget belongs to.
     *
     * @return BelongsTo<ClockifyConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ClockifyConnection::class);
    }

    /**
     * The workspace the budget belongs to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }
}
