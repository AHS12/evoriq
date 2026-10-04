<?php

namespace App\Enums;

/**
 * A coarse grouping of notification types used by the preference UI so a user
 * can mute a whole area (e.g. Pipeline) in one switch. The English label is the
 * translation key; the frontend renders it through `t()`.
 */
enum NotificationCategory: string
{
    case PIPELINE = 'pipeline';
    case ACCOUNT = 'account';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::PIPELINE => 'Pipeline',
            self::ACCOUNT => 'Account',
            self::SYSTEM => 'System',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PIPELINE => 'Imports, exports, reports and syncs.',
            self::ACCOUNT => 'Invitations and account activity.',
            self::SYSTEM => 'Announcements, backups and system notices.',
        };
    }

    /**
     * All enum values, for validation rules.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
