<?php

namespace App\Models;

use App\Enums\SyncEntityType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ClockifyRawRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The last raw upstream payload for an entity, kept for recovery and repair
 * (SYNC-01, SYNC-05). Organization-owned.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $workspace_id
 * @property SyncEntityType $entity_type
 * @property string $clockify_id
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string $source
 * @property Carbon $fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id', 'workspace_id', 'entity_type', 'clockify_id', 'payload',
    'payload_hash', 'source', 'fetched_at',
])]
class ClockifyRawRecord extends Model
{
    /**
     * @use HasFactory<ClockifyRawRecordFactory>
     * @use BelongsToOrganization<ClockifyRawRecord>
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
            'payload' => 'array',
            'fetched_at' => 'datetime',
        ];
    }

    /**
     * The workspace the record belongs to.
     *
     * @return BelongsTo<ClockifyWorkspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(ClockifyWorkspace::class);
    }
}
