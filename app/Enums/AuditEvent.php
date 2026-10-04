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
    case STORAGE_UPDATED = 'storage_updated';

    case AVATAR_UPDATED = 'avatar_updated';
    case AVATAR_REMOVED = 'avatar_removed';

    case EXPORT_COMPLETED = 'export_completed';
    case EXPORT_FAILED = 'export_failed';
    case IMPORT_COMPLETED = 'import_completed';
    case IMPORT_FAILED = 'import_failed';
    case IMPORT_STARTED = 'import_started';
    case PROCESSING_CANCELLED = 'processing_cancelled';

    case CONNECTION_CREATED = 'connection_created';
    case CONNECTION_UPDATED = 'connection_updated';
    case CONNECTION_DISABLED = 'connection_disabled';
    case CONNECTION_ENABLED = 'connection_enabled';
    case CONNECTION_VERIFIED = 'connection_verified';
    case CONNECTION_VALIDATION_FAILED = 'connection_validation_failed';
    case CONNECTION_KEY_ROTATED = 'connection_key_rotated';
    case WORKSPACE_SELECTED = 'workspace_selected';

    case SYNC_RUN_STARTED = 'sync_run_started';
    case SYNC_RUN_COMPLETED = 'sync_run_completed';
    case SYNC_RUN_FAILED = 'sync_run_failed';
    case SYNC_RUN_CANCELLED = 'sync_run_cancelled';

    case BACKUP_TRIGGERED = 'backup_triggered';
    case BACKUP_COMPLETED = 'backup_completed';
    case BACKUP_FAILED = 'backup_failed';

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
            self::STORAGE_UPDATED => 'Remote storage updated',

            self::AVATAR_UPDATED => 'Avatar updated',
            self::AVATAR_REMOVED => 'Avatar removed',
            self::EXPORT_COMPLETED => 'Export completed',
            self::EXPORT_FAILED => 'Export failed',
            self::IMPORT_COMPLETED => 'Import completed',
            self::IMPORT_FAILED => 'Import failed',
            self::IMPORT_STARTED => 'Import started',
            self::PROCESSING_CANCELLED => 'Processing cancelled',
            self::CONNECTION_CREATED => 'Clockify connection created',
            self::CONNECTION_UPDATED => 'Clockify connection updated',
            self::CONNECTION_DISABLED => 'Clockify connection disabled',
            self::CONNECTION_ENABLED => 'Clockify connection enabled',
            self::CONNECTION_VERIFIED => 'Clockify connection verified',
            self::CONNECTION_VALIDATION_FAILED => 'Clockify connection validation failed',
            self::CONNECTION_KEY_ROTATED => 'Clockify connection key rotated',
            self::WORKSPACE_SELECTED => 'Workspace selected',
            self::SYNC_RUN_STARTED => 'Sync run started',
            self::SYNC_RUN_COMPLETED => 'Sync run completed',
            self::SYNC_RUN_FAILED => 'Sync run failed',
            self::SYNC_RUN_CANCELLED => 'Sync run cancelled',
            self::BACKUP_TRIGGERED => 'Backup triggered',
            self::BACKUP_COMPLETED => 'Backup completed',
            self::BACKUP_FAILED => 'Backup failed',
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
