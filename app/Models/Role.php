<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
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
