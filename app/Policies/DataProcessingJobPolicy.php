<?php

namespace App\Policies;

use App\Models\DataProcessingJob;
use App\Models\User;

class DataProcessingJobPolicy
{
    /**
     * Determine whether the user can view any jobs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['export.view', 'export.view.all']);
    }

    /**
     * Determine whether the user can view the job.
     */
    public function view(User $user, DataProcessingJob $job): bool
    {
        return $user->hasPermissionTo('export.view.all') || $job->user_id === $user->id;
    }

    /**
     * Determine whether the user can create jobs.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('export.create');
    }

    /**
     * Determine whether the user can download the job's file.
     */
    public function download(User $user, DataProcessingJob $job): bool
    {
        return $this->view($user, $job);
    }

    /**
     * Determine whether the user can delete the job.
     */
    public function delete(User $user, DataProcessingJob $job): bool
    {
        return $user->hasPermissionTo('export.delete');
    }
}
