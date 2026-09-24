<?php

namespace App\Enums;

/**
 * Who a notification is addressed to.
 *
 * This is not a polymorphic morph map — `target_id` holds a user or role id
 * (stored as a string) and is only meaningful for the USER and ROLE types.
 */
enum NotificationTargetType: string
{
    case ALL = 'all';
    case USER = 'user';
    case ROLE = 'role';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Everyone',
            self::USER => 'User',
            self::ROLE => 'Role',
        };
    }

    /**
     * Whether the target requires a `target_id`.
     */
    public function requiresId(): bool
    {
        return $this !== self::ALL;
    }
}
