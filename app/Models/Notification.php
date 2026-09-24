<?php

namespace App\Models;

use App\Enums\NotificationPriority;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property NotificationPriority $priority
 * @property string $title
 * @property string|null $body
 * @property string|null $action_url
 * @property array<string, mixed>|null $data
 * @property string|null $group_key
 * @property int|null $created_by
 * @property Carbon|null $expires_at
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $is_read
 * @property-read User|null $creator
 */
#[Fillable([
    'type', 'priority', 'title', 'body', 'action_url', 'data', 'group_key',
    'created_by', 'expires_at',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => NotificationPriority::class,
            'data' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The targets this notification was addressed to.
     *
     * @return HasMany<NotificationTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(NotificationTarget::class);
    }

    /**
     * The read receipts for this notification.
     *
     * @return HasMany<NotificationRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class);
    }

    /**
     * The user that created the notification.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Only notifications that have not expired.
     *
     * @param  Builder<Notification>  $query
     * @return Builder<Notification>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Whether the notification has passed its expiry.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
