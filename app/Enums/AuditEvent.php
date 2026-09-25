<?php

namespace App\Enums;

enum AuditEvent: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
    case RESTORED = 'restored';

    case LOGIN = 'login';
    case LOGOUT = 'logout';
    case LOGIN_FAILED = 'login_failed';
    case LOCKOUT = 'lockout';
    case PASSWORD_RESET = 'password_reset';
    case PASSWORD_UPDATED = 'password_updated';
    case EMAIL_VERIFIED = 'email_verified';
    case TWO_FACTOR_ENABLED = 'two_factor_enabled';
    case TWO_FACTOR_DISABLED = 'two_factor_disabled';
    case TWO_FACTOR_FAILED = 'two_factor_failed';

    case ROLE_ASSIGNED = 'role_assigned';
    case PERMISSION_GRANTED = 'permission_granted';
    case PERMISSION_REVOKED = 'permission_revoked';

    case USER_SUSPENDED = 'user_suspended';
    case USER_REACTIVATED = 'user_reactivated';

    case SETTING_UPDATED = 'setting_updated';
    case NOTIFICATION_PREFERENCES_UPDATED = 'notification_preferences_updated';

    case AVATAR_UPDATED = 'avatar_updated';
    case AVATAR_REMOVED = 'avatar_removed';

    case EXPORT_COMPLETED = 'export_completed';
    case EXPORT_FAILED = 'export_failed';
    case IMPORT_COMPLETED = 'import_completed';
    case IMPORT_FAILED = 'import_failed';
    case PROCESSING_CANCELLED = 'processing_cancelled';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::RESTORED => 'Restored',
            self::LOGIN => 'Login',
            self::LOGOUT => 'Logout',
            self::LOGIN_FAILED => 'Failed login',
            self::LOCKOUT => 'Login locked out',
            self::PASSWORD_RESET => 'Password reset',
            self::PASSWORD_UPDATED => 'Password updated',
            self::EMAIL_VERIFIED => 'Email verified',
            self::TWO_FACTOR_ENABLED => 'Two-factor enabled',
            self::TWO_FACTOR_DISABLED => 'Two-factor disabled',
            self::TWO_FACTOR_FAILED => 'Two-factor failed',
            self::ROLE_ASSIGNED => 'Role assigned',
            self::PERMISSION_GRANTED => 'Permission granted',
            self::PERMISSION_REVOKED => 'Permission revoked',
            self::USER_SUSPENDED => 'User suspended',
            self::USER_REACTIVATED => 'User reactivated',
            self::SETTING_UPDATED => 'Setting updated',
            self::NOTIFICATION_PREFERENCES_UPDATED => 'Notification preferences updated',

            self::AVATAR_UPDATED => 'Avatar updated',
            self::AVATAR_REMOVED => 'Avatar removed',
            self::EXPORT_COMPLETED => 'Export completed',
            self::EXPORT_FAILED => 'Export failed',
            self::IMPORT_COMPLETED => 'Import completed',
            self::IMPORT_FAILED => 'Import failed',
            self::PROCESSING_CANCELLED => 'Processing cancelled',
        };
    }

    public function isModelEvent(): bool
    {
        return match ($this) {
            self::CREATED, self::UPDATED, self::DELETED, self::RESTORED => true,
            default => false,
        };
    }
}
