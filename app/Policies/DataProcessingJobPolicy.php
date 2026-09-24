<?php

namespace App\Policies;

use App\Enums\DataEntity;
use App\Enums\DataProcessingJobType;
use App\Models\DataProcessingJob;
use App\Models\User;

class DataProcessingJobPolicy
{
    /**
     * Determine whether the user can open the Data Processing Center.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAny($user, ['data-processing.view', 'data-processing.view.all']);
    }

    /**
     * Determine whether the user can view the job.
     */
    public function view(User $user, DataProcessingJob $job): bool
    {
        return $job->user_id === $user->id || $this->hasAny($user, ['data-processing.view.all']);
    }

    /**
     * Determine whether the user can queue a job of the given type for an entity.
     *
     * Allowed with the module-level permission (e.g. `user.export`) or the global
     * operation permission (`export.create` / `import.create`).
     */
    public function create(User $user, DataProcessingJobType $type, DataEntity $entity): bool
    {
        $module = "{$entity->permissionKey()}.{$type->value}";
        $global = $type === DataProcessingJobType::IMPORT ? 'import.create' : 'export.create';

        return $this->hasAny($user, [$module, $global]);
    }

    /**
     * Determine whether the user can cancel, retry or duplicate the job.
     */
    public function manage(User $user, DataProcessingJob $job): bool
    {
        return $job->user_id === $user->id || $this->hasAny($user, ['data-processing.manage']);
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
        return $job->user_id === $user->id || $this->hasAny($user, ['data-processing.delete']);
    }

    /**
     * Whether the user holds any of the given permissions (never throws for
     * permissions that are not registered yet, e.g. future `{entity}.report`).
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
