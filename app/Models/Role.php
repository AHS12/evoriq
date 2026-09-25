<?php

namespace App\Models;

use App\Enums\AuditLogName;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_system
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read int|null $users_count
 */
class Role extends SpatieRole
{
    use LogsActivity;

    /**
     * Configure the automatic audit trail for the model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(AuditLogName::RBAC)
            ->logOnly(['name', 'is_system'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $event): string => "Role {$event}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
