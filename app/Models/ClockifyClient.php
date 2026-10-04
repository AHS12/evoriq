<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A Clockify client dimension (ENT-03). `archived` is Clockify's own flag;
 * `deleted_at` is only ever set by an upstream deletion (SYNC-07). Soft-deleted
 * so historical facts keep their client reference. Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property string $clockify_id
 * @property string $name
 * @property string|null $email
 * @property string|null $address
 * @property string|null $note
 * @property string|null $currency_code
 * @property bool $archived
 * @property Carbon|null $archived_at
 * @property array<string, mixed>|null $raw_data
 * @property Carbon|null $synced_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'clockify_id', 'name', 'email', 'address',
    'note', 'currency_code', 'archived', 'archived_at', 'raw_data', 'synced_at',
    'deleted_at',
])]
class ClockifyClient extends Model
{
    /**
     * @use HasFactory<ClockifyClientFactory>
     * @use BelongsToOrganization<ClockifyClient>
     */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived' => 'boolean',
            'archived_at' => 'datetime',
            'raw_data' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }
}
