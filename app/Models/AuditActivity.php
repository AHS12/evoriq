<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Application-owned activity model. Extends the spatie base so we can add
 * scopes and future schema tweaks without touching package internals.
 *
 * @property int $id
 * @property string|null $log_name
 * @property string $description
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string|null $causer_type
 * @property int|string|null $causer_id
 * @property string|null $event
 * @property Collection<string, mixed>|null $attribute_changes
 * @property Collection<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class AuditActivity extends Activity
{
    /**
     * Limit results to entries caused by the given user.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCausedByUser(Builder $query, int $userId): Builder
    {
        return $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $userId);
    }
}
