<?php

namespace App\Policies;

use App\Models\Upload;
use App\Models\User;

class UploadPolicy
{
    /**
     * Determine whether the user can view any uploads.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['file.view', 'file.view.all']);
    }

    /**
     * Determine whether the user can view the upload.
     */
    public function view(User $user, Upload $upload): bool
    {
        return $user->hasPermissionTo('file.view.all')
            || ($user->hasPermissionTo('file.view') && $upload->created_by === $user->id);
    }

    /**
     * Determine whether the user can create uploads.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('file.create');
    }

    /**
     * Determine whether the user can delete the upload.
     */
    public function delete(User $user, Upload $upload): bool
    {
        return $user->hasPermissionTo('file.delete');
    }
}
