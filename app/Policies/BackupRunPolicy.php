<?php

namespace App\Policies;

use App\Models\BackupRun;
use App\Models\User;

class BackupRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('backup.view');
    }

    public function view(User $user, BackupRun $backupRun): bool
    {
        return $user->hasPermissionTo('backup.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('backup.manage');
    }
}
