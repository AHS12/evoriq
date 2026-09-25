<?php

namespace App\Policies;

use App\Models\AuditActivity;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can browse the audit log at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['audit.view', 'audit.view.all']);
    }

    /**
     * Users with `audit.view` may only see entries they caused themselves;
     * `audit.view.all` unlocks the full trail.
     */
    public function view(User $user, AuditActivity $auditActivity): bool
    {
        return $user->hasPermissionTo('audit.view.all')
            || ($user->hasPermissionTo('audit.view')
                && $auditActivity->causer_type === (new User)->getMorphClass()
                && $auditActivity->causer_id === $user->id);
    }

    /**
     * Determine whether the user can prune the audit log.
     */
    public function prune(User $user): bool
    {
        return $user->hasPermissionTo('audit.manage');
    }
}
