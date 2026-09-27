<?php

namespace App\Enums;

use Closure;
use DateTimeZone;

/**
 * Global (single-tenant) application settings.
 *
 * Values are persisted through `ahs12/laravel-setanjo` and synced with
 * `php artisan settings:sync`. Settings with a `null` default are managed by
 * the application or the user (e.g. the setup flag, SMTP credentials) and are
 * never seeded.
 */
enum SettingKey: string
{
    case SYSTEM_NAME = 'system_name';
    case APP_LOCALE = 'app_locale';
    case TIMEZONE = 'timezone';
    case DATE_FORMAT = 'date_format';
    case WEEK_START = 'week_start';

    case EXPORT_CLEANUP_DAYS = 'export_cleanup_days';

    case NOTIFICATION_ENABLED = 'notification_enabled';
    case NOTIFICATION_RETENTION_DAYS = 'notification_retention_days';

    case AUDIT_RETENTION_AUTH = 'audit_retention_auth';
    case AUDIT_RETENTION_SECURITY = 'audit_retention_security';
    case AUDIT_RETENTION_RBAC = 'audit_retention_rbac';
    case AUDIT_RETENTION_SETTINGS = 'audit_retention_settings';
    case AUDIT_RETENTION_DOMAIN = 'audit_retention_domain';

    case BACKUP_ENABLED = 'backup_enabled';
    case BACKUP_FREQUENCY = 'backup_frequency';
    case BACKUP_TIME = 'backup_time';
    case BACKUP_SEND_TO_REMOTE = 'backup_send_to_remote';
    case BACKUP_KEEP_DAYS = 'backup_keep_days';
    case BACKUP_MAX_STORAGE_MB = 'backup_max_storage_mb';
    case BACKUP_MAX_AGE_DAYS = 'backup_max_age_days';
    case BACKUP_NOTIFY_ON_FAILURE = 'backup_notify_on_failure';
    case BACKUP_EMAIL_ENABLED = 'backup_email_enabled';
    case BACKUP_EMAIL_RECIPIENTS = 'backup_email_recipients';

    case MAIL_MAILER = 'mail_mailer';
    case MAIL_HOST = 'mail_host';
    case MAIL_PORT = 'mail_port';
    case MAIL_USERNAME = 'mail_username';
    case MAIL_PASSWORD = 'mail_password';
    case MAIL_ENCRYPTION = 'mail_encryption';
    case MAIL_FROM_ADDRESS = 'mail_from_address';
    case MAIL_FROM_NAME = 'mail_from_name';

    case SETUP_COMPLETED_AT = 'setup_completed_at';

    /**
     * The UI group this setting belongs to.
     */
    public function group(): string
    {
        return match ($this) {
            self::SYSTEM_NAME, self::APP_LOCALE, self::TIMEZONE, self::DATE_FORMAT, self::WEEK_START,
            self::EXPORT_CLEANUP_DAYS => 'general',
            self::NOTIFICATION_ENABLED, self::NOTIFICATION_RETENTION_DAYS => 'notifications',
            self::AUDIT_RETENTION_AUTH, self::AUDIT_RETENTION_SECURITY, self::AUDIT_RETENTION_RBAC,
            self::AUDIT_RETENTION_SETTINGS, self::AUDIT_RETENTION_DOMAIN => 'audit',
            self::BACKUP_ENABLED, self::BACKUP_FREQUENCY, self::BACKUP_TIME, self::BACKUP_SEND_TO_REMOTE,
            self::BACKUP_KEEP_DAYS, self::BACKUP_MAX_STORAGE_MB, self::BACKUP_MAX_AGE_DAYS,
            self::BACKUP_NOTIFY_ON_FAILURE, self::BACKUP_EMAIL_ENABLED,
            self::BACKUP_EMAIL_RECIPIENTS => 'backup',
            self::MAIL_MAILER, self::MAIL_HOST, self::MAIL_PORT, self::MAIL_USERNAME,
            self::MAIL_PASSWORD, self::MAIL_ENCRYPTION, self::MAIL_FROM_ADDRESS,
            self::MAIL_FROM_NAME => 'mail',
            self::SETUP_COMPLETED_AT => 'system',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_NAME => __('Workspace name'),
            self::APP_LOCALE => __('Language'),
            self::TIMEZONE => __('Timezone'),
            self::DATE_FORMAT => __('Date format'),
            self::WEEK_START => __('Week starts on'),
            self::EXPORT_CLEANUP_DAYS => __('Export retention (days)'),
            self::NOTIFICATION_ENABLED => __('Enable notifications'),
            self::NOTIFICATION_RETENTION_DAYS => __('Notification retention (days)'),
            self::AUDIT_RETENTION_AUTH => __('Auth log retention'),
            self::AUDIT_RETENTION_SECURITY => __('Security log retention'),
            self::AUDIT_RETENTION_RBAC => __('Roles & permissions log retention'),
            self::AUDIT_RETENTION_SETTINGS => __('Settings log retention'),
            self::AUDIT_RETENTION_DOMAIN => __('Domain log retention'),
            self::BACKUP_ENABLED => __('Enable backups'),
            self::BACKUP_FREQUENCY => __('Backup frequency'),
            self::BACKUP_TIME => __('Backup time'),
            self::BACKUP_SEND_TO_REMOTE => __('Also send backups to remote storage'),
            self::BACKUP_KEEP_DAYS => __('Keep all backups for (days)'),
            self::BACKUP_MAX_STORAGE_MB => __('Maximum backup storage (MB)'),
            self::BACKUP_MAX_AGE_DAYS => __('Backup freshness check (days)'),
            self::BACKUP_NOTIFY_ON_FAILURE => __('Notify on backup failure'),
            self::BACKUP_EMAIL_ENABLED => __('Email backup archives'),
            self::BACKUP_EMAIL_RECIPIENTS => __('Email recipients'),
            self::MAIL_MAILER => __('Mailer'),
            self::MAIL_HOST => __('SMTP host'),
            self::MAIL_PORT => __('SMTP port'),
            self::MAIL_USERNAME => __('SMTP username'),
            self::MAIL_PASSWORD => __('SMTP password'),
            self::MAIL_ENCRYPTION => __('Encryption'),
            self::MAIL_FROM_ADDRESS => __('From address'),
            self::MAIL_FROM_NAME => __('From name'),
            self::SETUP_COMPLETED_AT => __('Setup completed at'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SYSTEM_NAME => __('Application display name'),
            self::APP_LOCALE => __('Language for your account. New users and guests start with the default.'),
            self::TIMEZONE => __('Default timezone for dates and reports'),
            self::DATE_FORMAT => __('How dates are displayed across the app'),
            self::WEEK_START => __('First day of the week for reports'),
            self::EXPORT_CLEANUP_DAYS => __('Days to keep completed export files before cleanup'),
            self::NOTIFICATION_ENABLED => __('Master switch for creating in-app notifications'),
            self::NOTIFICATION_RETENTION_DAYS => __('Days to keep notifications before they are pruned'),
            self::AUDIT_RETENTION_AUTH => __('How long login and logout events are kept before pruning'),
            self::AUDIT_RETENTION_SECURITY => __('How long security events (suspensions, failed logins, 2FA) are kept before pruning'),
            self::AUDIT_RETENTION_RBAC => __('How long role and permission changes are kept before pruning'),
            self::AUDIT_RETENTION_SETTINGS => __('How long setting changes are kept before pruning'),
            self::AUDIT_RETENTION_DOMAIN => __('How long domain events (exports, imports, uploads) are kept before pruning'),
            self::BACKUP_ENABLED => __('Take scheduled backups of files and the database'),
            self::BACKUP_FREQUENCY => __('How often a scheduled backup runs'),
            self::BACKUP_TIME => __('Time of day the scheduled backup runs'),
            self::BACKUP_SEND_TO_REMOTE => __('Keep the local backup and also send a copy to the remote storage when it is configured'),
            self::BACKUP_KEEP_DAYS => __('Days to keep every backup before cleanup keeps only dailies, weeklies and monthlies'),
            self::BACKUP_MAX_STORAGE_MB => __('Oldest backups are removed once backups use more than this many megabytes'),
            self::BACKUP_MAX_AGE_DAYS => __('A backup older than this many days is flagged as unhealthy'),
            self::BACKUP_NOTIFY_ON_FAILURE => __('Send an in-app notification when a backup fails or is unhealthy'),
            self::BACKUP_EMAIL_ENABLED => __('Email the backup archive to backup managers after each successful backup (skipped for very large archives)'),
            self::BACKUP_EMAIL_RECIPIENTS => __('Comma-separated email addresses that receive the backup archive. Leave empty to email the backup managers.'),
            self::MAIL_MAILER => __('Transport used to send email'),
            self::MAIL_HOST => __('SMTP server hostname'),
            self::MAIL_PORT => __('SMTP server port'),
            self::MAIL_USERNAME => __('SMTP username'),
            self::MAIL_PASSWORD => __('SMTP password (stored encrypted)'),
            self::MAIL_ENCRYPTION => __('SMTP encryption method'),
            self::MAIL_FROM_ADDRESS => __('Default sender address'),
            self::MAIL_FROM_NAME => __('Default sender name'),
            self::SETUP_COMPLETED_AT => __('Timestamp of the one-time first-run setup'),
        };
    }

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::SYSTEM_NAME => 'Evoriq',
            self::APP_LOCALE => AppLocale::EN->value,
            self::TIMEZONE => 'UTC',
            self::DATE_FORMAT => 'Y-m-d',
            self::WEEK_START => 'monday',
            self::EXPORT_CLEANUP_DAYS => 7,
            self::NOTIFICATION_ENABLED => true,
            self::NOTIFICATION_RETENTION_DAYS => 90,
            self::AUDIT_RETENTION_AUTH => 180,
            self::AUDIT_RETENTION_SECURITY => 365,
            self::AUDIT_RETENTION_RBAC => 365,
            self::AUDIT_RETENTION_SETTINGS => 180,
            self::AUDIT_RETENTION_DOMAIN => 90,
            self::BACKUP_ENABLED => true,
            self::BACKUP_FREQUENCY => 'daily',
            self::BACKUP_TIME => '01:30',
            self::BACKUP_SEND_TO_REMOTE => false,
            self::BACKUP_KEEP_DAYS => 14,
            self::BACKUP_MAX_STORAGE_MB => 5000,
            self::BACKUP_MAX_AGE_DAYS => 1,
            self::BACKUP_NOTIFY_ON_FAILURE => true,
            self::BACKUP_EMAIL_ENABLED => false,
            self::BACKUP_EMAIL_RECIPIENTS => null,
            default => null,
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::EXPORT_CLEANUP_DAYS, self::MAIL_PORT, self::NOTIFICATION_RETENTION_DAYS,
            self::BACKUP_KEEP_DAYS, self::BACKUP_MAX_STORAGE_MB, self::BACKUP_MAX_AGE_DAYS => 'integer',
            self::NOTIFICATION_ENABLED, self::BACKUP_ENABLED, self::BACKUP_NOTIFY_ON_FAILURE,
            self::BACKUP_SEND_TO_REMOTE, self::BACKUP_EMAIL_ENABLED => 'boolean',
            self::BACKUP_FREQUENCY, self::BACKUP_TIME => 'select',
            self::AUDIT_RETENTION_AUTH, self::AUDIT_RETENTION_SECURITY, self::AUDIT_RETENTION_RBAC,
            self::AUDIT_RETENTION_SETTINGS, self::AUDIT_RETENTION_DOMAIN => 'select',
            self::TIMEZONE, self::MAIL_MAILER, self::MAIL_ENCRYPTION, self::WEEK_START,
            self::APP_LOCALE => 'select',
            default => 'string',
        };
    }

    /**
     * Options for `select` fields.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function options(): array
    {
        return match ($this) {
            self::TIMEZONE => array_map(
                static fn (string $timezone): array => ['label' => $timezone, 'value' => $timezone],
                DateTimeZone::listIdentifiers(),
            ),
            self::MAIL_MAILER => [
                ['label' => __('SMTP'), 'value' => 'smtp'],
                ['label' => __('Log'), 'value' => 'log'],
                ['label' => __('Array (testing)'), 'value' => 'array'],
            ],
            self::MAIL_ENCRYPTION => [
                ['label' => __('None'), 'value' => 'none'],
                ['label' => __('TLS'), 'value' => 'tls'],
                ['label' => __('SSL'), 'value' => 'ssl'],
            ],
            self::WEEK_START => [
                ['label' => __('Monday'), 'value' => 'monday'],
                ['label' => __('Tuesday'), 'value' => 'tuesday'],
                ['label' => __('Wednesday'), 'value' => 'wednesday'],
                ['label' => __('Thursday'), 'value' => 'thursday'],
                ['label' => __('Friday'), 'value' => 'friday'],
                ['label' => __('Saturday'), 'value' => 'saturday'],
                ['label' => __('Sunday'), 'value' => 'sunday'],
            ],
            self::APP_LOCALE => AppLocale::options(),
            self::AUDIT_RETENTION_AUTH, self::AUDIT_RETENTION_SECURITY, self::AUDIT_RETENTION_RBAC,
            self::AUDIT_RETENTION_SETTINGS, self::AUDIT_RETENTION_DOMAIN => self::retentionOptions(),
            self::BACKUP_FREQUENCY => [
                ['label' => __('Daily'), 'value' => 'daily'],
                ['label' => __('Weekly'), 'value' => 'weekly'],
            ],
            self::BACKUP_TIME => self::backupTimeOptions(),
            default => [],
        };
    }

    /**
     * Retention choices shared by every audit log channel (value = days).
     *
     * @return array<int, array{label: string, value: string}>
     */
    private static function retentionOptions(): array
    {
        return [
            ['label' => __('6 months'), 'value' => '180'],
            ['label' => __('1 year'), 'value' => '365'],
            ['label' => __('2 years'), 'value' => '730'],
            ['label' => __('3 years'), 'value' => '1095'],
            ['label' => __('5 years'), 'value' => '1825'],
            ['label' => __('7 years'), 'value' => '2555'],
            ['label' => __('10 years'), 'value' => '3650'],
        ];
    }

    /**
     * Run-time choices for the scheduled backup time. Times inside the DST
     * transition window (02:00–03:00) are deliberately excluded.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private static function backupTimeOptions(): array
    {
        return [
            ['label' => '00:30', 'value' => '00:30'],
            ['label' => '01:30', 'value' => '01:30'],
            ['label' => '04:30', 'value' => '04:30'],
            ['label' => '05:30', 'value' => '05:30'],
            ['label' => '23:30', 'value' => '23:30'],
        ];
    }

    /**
     * Whether the value must be encrypted at rest and never sent to the client.
     */
    public function isSecret(): bool
    {
        return $this === self::MAIL_PASSWORD;
    }

    /**
     * Whether the setting can be edited through the Settings UI.
     */
    public function isConfigurable(): bool
    {
        return $this !== self::SETUP_COMPLETED_AT;
    }

    /**
     * Validation rules for this setting.
     *
     * @return array<int, string|Closure(string, mixed, Closure(string): void): void>
     */
    public function rules(): array
    {
        return match ($this) {
            self::SYSTEM_NAME => ['required', 'string', 'max:255'],
            self::APP_LOCALE => ['sometimes', 'required', 'in:'.implode(',', array_column(AppLocale::cases(), 'value'))],
            self::TIMEZONE => ['required', 'string', 'timezone'],
            self::DATE_FORMAT => ['required', 'string', 'max:20'],
            self::WEEK_START => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            self::EXPORT_CLEANUP_DAYS => ['required', 'integer', 'min:1', 'max:365'],
            self::NOTIFICATION_ENABLED => ['required', 'boolean'],
            self::NOTIFICATION_RETENTION_DAYS => ['required', 'integer', 'min:1', 'max:3650'],
            self::AUDIT_RETENTION_AUTH, self::AUDIT_RETENTION_SECURITY, self::AUDIT_RETENTION_RBAC,
            self::AUDIT_RETENTION_SETTINGS, self::AUDIT_RETENTION_DOMAIN => ['required', 'integer', 'min:30', 'max:3650'],
            self::BACKUP_ENABLED => ['required', 'boolean'],
            self::BACKUP_FREQUENCY => ['required', 'in:daily,weekly'],
            self::BACKUP_TIME => ['required', 'in:00:30,01:30,04:30,05:30,23:30'],
            self::BACKUP_SEND_TO_REMOTE => ['required', 'boolean'],
            self::BACKUP_KEEP_DAYS => ['required', 'integer', 'min:3', 'max:365'],
            self::BACKUP_MAX_STORAGE_MB => ['required', 'integer', 'min:100', 'max:1000000'],
            self::BACKUP_MAX_AGE_DAYS => ['required', 'integer', 'min:1', 'max:30'],
            self::BACKUP_NOTIFY_ON_FAILURE => ['required', 'boolean'],
            self::BACKUP_EMAIL_ENABLED => ['required', 'boolean'],
            self::BACKUP_EMAIL_RECIPIENTS => [
                'nullable', 'string', 'max:1000',
                function (string $attribute, mixed $value, Closure $fail): void {
                    foreach (preg_split('/[,\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('Each recipient must be a valid email address.'));
                        }
                    }
                },
            ],
            self::MAIL_MAILER => ['required', 'in:smtp,log,array'],
            self::MAIL_HOST => ['nullable', 'string', 'max:255'],
            self::MAIL_PORT => ['nullable', 'integer', 'min:1', 'max:65535'],
            self::MAIL_USERNAME => ['nullable', 'string', 'max:255'],
            self::MAIL_PASSWORD => ['nullable', 'string', 'max:255'],
            self::MAIL_ENCRYPTION => ['nullable', 'in:none,tls,ssl'],
            self::MAIL_FROM_ADDRESS => ['nullable', 'email', 'max:255'],
            self::MAIL_FROM_NAME => ['nullable', 'string', 'max:255'],
            self::SETUP_COMPLETED_AT => [],
        };
    }

    /**
     * Every configurable setting.
     *
     * @return array<int, self>
     */
    public static function configurable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $key): bool => $key->isConfigurable(),
        ));
    }

    /**
     * Configurable settings for a group.
     *
     * @return array<int, self>
     */
    public static function forGroup(string $group): array
    {
        return array_values(array_filter(
            self::configurable(),
            static fn (self $key): bool => $key->group() === $group,
        ));
    }

    /**
     * The configurable groups, keyed by group name.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'general' => __('General'),
            'notifications' => __('Notifications'),
            'audit' => __('Audit log'),
            'mail' => __('Mail'),
            'backup' => __('Backups'),
        ];
    }
}
