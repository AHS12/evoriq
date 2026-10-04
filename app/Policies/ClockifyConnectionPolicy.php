<?php

namespace App\Policies;

use App\Models\ClockifyConnection;
use App\Models\User;

/**
 * Authorization for Clockify connections (CONN-01, CONN-06). Permissions are
 * read safely so a not-yet-synced permission never throws.
 */
class ClockifyConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, ['connection.view', 'connection.view.all']);
    }

    public function view(User $user, ClockifyConnection $connection): bool
    {
        return $this->hasAny($user, ['connection.view', 'connection.view.all']);
    }

    public function create(User $user): bool
    {
        return $this->hasAny($user, ['connection.create']);
    }

    public function update(User $user, ClockifyConnection $connection): bool
    {
        return $this->manage($user);
    }

    public function reverify(User $user, ClockifyConnection $connection): bool
    {
        return $this->manage($user);
    }

    public function disable(User $user, ClockifyConnection $connection): bool
    {
        return $this->manage($user);
    }

    public function enable(User $user, ClockifyConnection $connection): bool
    {
        return $this->manage($user);
    }

    /**
     * Rotating the API key is a credential action: it needs the dedicated
     * `connection.credentials.update` grant (CONN-06).
     */
    public function rotateKey(User $user, ClockifyConnection $connection): bool
    {
        return $this->hasAny($user, ['connection.credentials.update']);
    }

    public function delete(User $user, ClockifyConnection $connection): bool
    {
        return $this->hasAny($user, ['connection.delete']);
    }

    /**
     * Any grant that may manage a connection's non-credential lifecycle.
     */
    private function manage(User $user): bool
    {
        return $this->hasAny($user, ['connection.update', 'connection.credentials.update']);
    }

    /**
     * Whether the user holds any of the given permissions.
     *
     * @param  array<int, string>  $permissions
     */
    private function hasAny(User $user, array $permissions): bool
    {
        $granted = $user->getAllPermissions()->pluck('name');

        foreach ($permissions as $permission) {
            if ($granted->contains($permission)) {
                return true;
            }
        }

        return false;
    }
}
