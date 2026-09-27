<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $clockify_organization_id
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int|null $users_count
 */
#[Fillable(['name', 'slug', 'clockify_organization_id', 'is_default', 'settings'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * The resolved default organization, cached per process.
     */
    protected static ?self $default = null;

    /**
     * Whether the default organization has been resolved.
     */
    protected static bool $defaultResolved = false;

    /**
     * The single organization flagged as the default, if one exists.
     */
    public static function default(): ?self
    {
        if (! static::$defaultResolved) {
            static::$defaultResolved = true;
            static::$default = static::query()->where('is_default', true)->first();
        }

        return static::$default;
    }

    /**
     * Forget the cached default organization (used by tests and seeders).
     */
    public static function forgetDefault(): void
    {
        static::$default = null;
        static::$defaultResolved = false;
    }

    /**
     * The users that belong to the organization.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }
}
