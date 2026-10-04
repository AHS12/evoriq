<?php

namespace App\Enums;

/**
 * The known kinds of in-app notification.
 *
 * The `notifications.type` column is a plain string so new types can be added
 * without a migration; these cases are the typed, UI-aware defaults.
 */
enum NotificationType: string
{
    case SYSTEM_ANNOUNCEMENT = 'system.announcement';
    case USER_INVITED = 'user.invited';
    case EXPORT_COMPLETED = 'export.completed';
    case EXPORT_FAILED = 'export.failed';
    case IMPORT_COMPLETED = 'import.completed';
    case IMPORT_FAILED = 'import.failed';
    case REPORT_COMPLETED = 'report.completed';
    case REPORT_FAILED = 'report.failed';
    case JOB_QUEUED = 'job.queued';
    case JOB_RETRYING = 'job.retrying';
    case JOB_CANCELLED = 'job.cancelled';
    case CLOCKIFY_SYNC_COMPLETED = 'clockify.sync.completed';
    case CLOCKIFY_SYNC_FAILED = 'clockify.sync.failed';
    case BACKUP_FAILED = 'backup.failed';
    case BACKUP_UNHEALTHY = 'backup.unhealthy';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_ANNOUNCEMENT => 'Announcement',
            self::USER_INVITED => 'User invited',
            self::EXPORT_COMPLETED => 'Export completed',
            self::EXPORT_FAILED => 'Export failed',
            self::IMPORT_COMPLETED => 'Import completed',
            self::IMPORT_FAILED => 'Import failed',
            self::REPORT_COMPLETED => 'Report completed',
            self::REPORT_FAILED => 'Report failed',
            self::JOB_QUEUED => 'Job queued',
            self::JOB_RETRYING => 'Job retrying',
            self::JOB_CANCELLED => 'Job cancelled',
            self::CLOCKIFY_SYNC_COMPLETED => 'Sync completed',
            self::CLOCKIFY_SYNC_FAILED => 'Sync failed',
            self::BACKUP_FAILED => 'Backup failed',
            self::BACKUP_UNHEALTHY => 'Backup unhealthy',
        };
    }

    public function defaultPriority(): NotificationPriority
    {
        return match ($this) {
            self::SYSTEM_ANNOUNCEMENT => NotificationPriority::INFO,
            self::USER_INVITED => NotificationPriority::SUCCESS,
            self::EXPORT_COMPLETED, self::IMPORT_COMPLETED, self::REPORT_COMPLETED,
            self::CLOCKIFY_SYNC_COMPLETED => NotificationPriority::SUCCESS,
            self::EXPORT_FAILED, self::IMPORT_FAILED, self::REPORT_FAILED,
            self::CLOCKIFY_SYNC_FAILED, self::BACKUP_FAILED => NotificationPriority::CRITICAL,
            self::JOB_RETRYING, self::JOB_CANCELLED, self::BACKUP_UNHEALTHY => NotificationPriority::WARNING,
            self::JOB_QUEUED => NotificationPriority::INFO,
        };
    }

    /**
     * The lucide-react icon name used by the frontend for this type.
     */
    public function icon(): string
    {
        return match ($this) {
            self::SYSTEM_ANNOUNCEMENT => 'megaphone',
            self::USER_INVITED => 'user-plus',
            self::EXPORT_COMPLETED => 'file-check',
            self::EXPORT_FAILED => 'file-x',
            self::IMPORT_COMPLETED => 'file-up',
            self::IMPORT_FAILED => 'file-x',
            self::REPORT_COMPLETED => 'file-text',
            self::REPORT_FAILED => 'file-x',
            self::JOB_QUEUED => 'list-clock',
            self::JOB_RETRYING => 'rotate-cw',
            self::JOB_CANCELLED => 'ban',
            self::CLOCKIFY_SYNC_COMPLETED => 'refresh-cw',
            self::CLOCKIFY_SYNC_FAILED => 'alert-triangle',
            self::BACKUP_FAILED => 'archive-x',
            self::BACKUP_UNHEALTHY => 'shield-alert',
        };
    }

    /**
     * The coarse category this type belongs to (PIPE-11), used for the
     * preference grouping/muting.
     */
    public function category(): NotificationCategory
    {
        return match ($this) {
            self::USER_INVITED => NotificationCategory::ACCOUNT,
            self::SYSTEM_ANNOUNCEMENT, self::BACKUP_FAILED, self::BACKUP_UNHEALTHY => NotificationCategory::SYSTEM,
            default => NotificationCategory::PIPELINE,
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

    /**
     * The notification type for a finished data processing job.
     */
    public static function forJob(DataProcessingJobType $type, DataProcessingJobStatus $status): self
    {
        if ($status === DataProcessingJobStatus::CANCELLED) {
            return self::JOB_CANCELLED;
        }

        $failed = $status === DataProcessingJobStatus::FAILED;

        return match ($type) {
            DataProcessingJobType::IMPORT => $failed ? self::IMPORT_FAILED : self::IMPORT_COMPLETED,
            DataProcessingJobType::EXPORT => $failed ? self::EXPORT_FAILED : self::EXPORT_COMPLETED,
            DataProcessingJobType::REPORT => $failed ? self::REPORT_FAILED : self::REPORT_COMPLETED,
        };
    }
}
