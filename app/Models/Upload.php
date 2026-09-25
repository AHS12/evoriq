<?php

namespace App\Models;

use App\Enums\AuditLogName;
use App\Enums\MediaCollection;
use App\Enums\UploadType;
use Database\Factories\UploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property string $uuid
 * @property UploadType $type
 * @property int|null $media_id
 * @property string|null $url
 * @property string|null $name
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 */
#[Fillable([
    'uuid', 'type', 'media_id', 'url', 'name', 'file_name', 'mime_type', 'size', 'created_by', 'updated_by',
])]
class Upload extends Model implements HasMedia
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory;

    use InteractsWithMedia;
    use LogsActivity;

    /**
     * Configure the automatic audit trail for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(AuditLogName::DOMAIN)
            ->logOnly(['name', 'type', 'size'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => "Upload {$event}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UploadType::class,
            'size' => 'integer',
        ];
    }

    /**
     * Register the media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollection::UPLOAD->value)->singleFile();
    }

    /**
     * Register the media conversions for the model.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $thumb = $this->addMediaConversion('thumb');

        $thumb->nonQueued();
        $thumb->width(320);
        $thumb->height(320);
    }

    /**
     * The user that created the upload.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
