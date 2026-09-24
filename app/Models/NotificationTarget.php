<?php

namespace App\Models;

use App\Enums\NotificationTargetType;
use Database\Factories\NotificationTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $notification_id
 * @property NotificationTargetType $target_type
 * @property int|null $target_id
 * @property-read Notification $notification
 */
#[Fillable(['notification_id', 'target_type', 'target_id'])]
class NotificationTarget extends Model
{
    /** @use HasFactory<NotificationTargetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => NotificationTargetType::class,
            'target_id' => 'integer',
        ];
    }

    /**
     * The notification this target belongs to.
     *
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
