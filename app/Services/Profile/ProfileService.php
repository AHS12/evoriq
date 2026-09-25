<?php

namespace App\Services\Profile;

use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Enums\MediaCollection;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;

/**
 * Self-service profile operations (avatar management). Profile field changes
 * (name/email) are captured automatically by the User model's audit trail.
 */
class ProfileService
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    /**
     * Store a newly uploaded avatar for the user.
     */
    public function updateAvatar(User $user, UploadedFile $file): void
    {
        $user->addMedia($file)->toMediaCollection(MediaCollection::PROFILE->value);

        $this->audit->record(
            AuditEvent::AVATAR_UPDATED,
            $user,
            actor: $user,
            channel: AuditLogName::SECURITY,
        );
    }

    /**
     * Remove the user's avatar.
     */
    public function removeAvatar(User $user): void
    {
        $user->clearMediaCollection(MediaCollection::PROFILE->value);

        $this->audit->record(
            AuditEvent::AVATAR_REMOVED,
            $user,
            actor: $user,
            channel: AuditLogName::SECURITY,
        );
    }
}
