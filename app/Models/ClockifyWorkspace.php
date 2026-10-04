<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyWorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Clockify workspace discovered through a connection (CONN-03, populated
 * during CONN-02 verification). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $connection_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $subdomain
 * @property string|null $currency
 * @property string|null $time_zone
 * @property string|null $week_start
 * @property bool|null $default_billable
 * @property string|null $default_hourly_rate
 * @property string|null $default_cost_rate
 * @property string|null $feature_subscription_type
 * @property string|null $cake_organization_id
 * @property array<string, mixed>|null $features
 * @property array<string, mixed>|null $workspace_settings
 * @property bool $active
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $change_feed_cursor_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'connection_id', 'clockify_id', 'name', 'subdomain',
    'currency', 'time_zone', 'week_start', 'default_billable',
    'default_hourly_rate', 'default_cost_rate', 'feature_subscription_type',
    'cake_organization_id', 'features', 'workspace_settings', 'active',
    'raw_data', 'synced_at', 'change_feed_cursor_at',
])]
class ClockifyWorkspace extends Model
{
    /**
     * @use HasFactory<ClockifyWorkspaceFactory>
     * @use BelongsToOrganization<ClockifyWorkspace>
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
            'features' => 'array',
            'workspace_settings' => 'array',
            'raw_data' => 'array',
            'default_billable' => 'boolean',
            'default_hourly_rate' => 'decimal:2',
            'default_cost_rate' => 'decimal:2',
            'active' => 'boolean',
            'synced_at' => 'datetime',
            'change_feed_cursor_at' => 'datetime',
        ];
    }

    /**
     * The connection the workspace was discovered through.
     *
     * @return BelongsTo<ClockifyConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ClockifyConnection::class);
    }
}
