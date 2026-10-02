<?php

namespace App\Models;

use App\Enums\ApiRegion;
use App\Enums\ConnectionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single encrypted Clockify API credential and its endpoint/plan profile
 * (CONN-01). Organization-owned; credentials are never serialized.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property string $name
 * @property string $api_key
 * @property string|null $addon_token
 * @property ApiRegion $region
 * @property string $base_url
 * @property string $reports_base_url
 * @property string|null $subdomain
 * @property string|null $workspace_id
 * @property string|null $feature_subscription_type
 * @property array<string, mixed>|null $features
 * @property int|null $webhook_limit
 * @property int|null $requests_per_hour
 * @property int|null $requests_per_second
 * @property ConnectionStatus $status
 * @property Carbon|null $last_verified_at
 * @property string|null $last_error
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'name', 'api_key', 'addon_token', 'region',
    'base_url', 'reports_base_url', 'subdomain', 'workspace_id',
    'feature_subscription_type', 'features', 'webhook_limit',
    'requests_per_hour', 'requests_per_second', 'status',
    'last_verified_at', 'last_error', 'created_by',
])]
#[Hidden(['api_key', 'addon_token'])]
class ClockifyConnection extends Model
{
    /**
     * @use HasFactory<ClockifyConnectionFactory>
     * @use BelongsToOrganization<ClockifyConnection>
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
            'api_key' => 'encrypted',
            'addon_token' => 'encrypted',
            'region' => ApiRegion::class,
            'status' => ConnectionStatus::class,
            'features' => 'array',
            'webhook_limit' => 'integer',
            'requests_per_hour' => 'integer',
            'requests_per_second' => 'integer',
            'last_verified_at' => 'datetime',
        ];
    }

    /**
     * The user that created the connection.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The workspaces discovered through this connection (CONN-03).
     *
     * @return HasMany<ClockifyWorkspace, $this>
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(ClockifyWorkspace::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Whether the connection is on Clockify's Free plan (hourly budget). Plan
     * detection lands in CONN-02; here an hourly-only profile means Free.
     */
    public function isFreePlan(): bool
    {
        return $this->requests_per_hour !== null && $this->requests_per_second === null;
    }

    /**
     * The effective request budget for this connection (CONN-01 §3).
     *
     * @return array{mode: 'hourly'|'per_second', limit: int, requests_per_hour: int|null, requests_per_second: int|null}
     */
    public function rateProfile(): array
    {
        if ($this->isFreePlan()) {
            $limit = (int) $this->requests_per_hour;

            return [
                'mode' => 'hourly',
                'limit' => $limit,
                'requests_per_hour' => $limit,
                'requests_per_second' => null,
            ];
        }

        $limit = $this->requests_per_second
            ?? (int) config('clockify.rate_limit.requests_per_second', 50);

        return [
            'mode' => 'per_second',
            'limit' => $limit,
            'requests_per_hour' => null,
            'requests_per_second' => $limit,
        ];
    }
}
