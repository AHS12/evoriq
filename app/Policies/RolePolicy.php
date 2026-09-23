<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('role.view.all');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('role.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('role.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('role.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if ($role->is_system) {
            return false;
        }

        return $user->hasPermissionTo('role.delete');
    }

    public function restore(User $user, Role $role): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $user->isSuperAdmin();
    }
}
