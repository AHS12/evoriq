<?php

namespace App\Enums;

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
    case TIMEZONE = 'timezone';
    case DATE_FORMAT = 'date_format';
    case WEEK_START = 'week_start';

    case EXPORT_CLEANUP_DAYS = 'export_cleanup_days';

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
            self::SYSTEM_NAME, self::TIMEZONE, self::DATE_FORMAT, self::WEEK_START,
            self::EXPORT_CLEANUP_DAYS => 'general',
            self::MAIL_MAILER, self::MAIL_HOST, self::MAIL_PORT, self::MAIL_USERNAME,
            self::MAIL_PASSWORD, self::MAIL_ENCRYPTION, self::MAIL_FROM_ADDRESS,
            self::MAIL_FROM_NAME => 'mail',
            self::SETUP_COMPLETED_AT => 'system',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_NAME => 'Workspace name',
            self::TIMEZONE => 'Timezone',
            self::DATE_FORMAT => 'Date format',
            self::WEEK_START => 'Week starts on',
            self::EXPORT_CLEANUP_DAYS => 'Export retention (days)',
            self::MAIL_MAILER => 'Mailer',
            self::MAIL_HOST => 'SMTP host',
            self::MAIL_PORT => 'SMTP port',
            self::MAIL_USERNAME => 'SMTP username',
            self::MAIL_PASSWORD => 'SMTP password',
            self::MAIL_ENCRYPTION => 'Encryption',
            self::MAIL_FROM_ADDRESS => 'From address',
            self::MAIL_FROM_NAME => 'From name',
            self::SETUP_COMPLETED_AT => 'Setup completed at',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SYSTEM_NAME => 'Application display name',
            self::TIMEZONE => 'Default timezone for dates and reports',
            self::DATE_FORMAT => 'How dates are displayed across the app',
            self::WEEK_START => 'First day of the week for reports',
            self::EXPORT_CLEANUP_DAYS => 'Days to keep completed export files before cleanup',
            self::MAIL_MAILER => 'Transport used to send email',
            self::MAIL_HOST => 'SMTP server hostname',
            self::MAIL_PORT => 'SMTP server port',
            self::MAIL_USERNAME => 'SMTP username',
            self::MAIL_PASSWORD => 'SMTP password (stored encrypted)',
            self::MAIL_ENCRYPTION => 'SMTP encryption method',
            self::MAIL_FROM_ADDRESS => 'Default sender address',
            self::MAIL_FROM_NAME => 'Default sender name',
            self::SETUP_COMPLETED_AT => 'Timestamp of the one-time first-run setup',
        };
    }

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::SYSTEM_NAME => 'Evoriq',
            self::TIMEZONE => 'UTC',
            self::DATE_FORMAT => 'Y-m-d',
            self::WEEK_START => 'monday',
            self::EXPORT_CLEANUP_DAYS => 7,
            default => null,
        };
    }

    public function type(): string
    {
        return match ($this) {
            self::EXPORT_CLEANUP_DAYS, self::MAIL_PORT => 'integer',
            self::TIMEZONE, self::MAIL_MAILER, self::MAIL_ENCRYPTION, self::WEEK_START => 'select',
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
                ['label' => 'SMTP', 'value' => 'smtp'],
                ['label' => 'Log', 'value' => 'log'],
                ['label' => 'Array (testing)', 'value' => 'array'],
            ],
            self::MAIL_ENCRYPTION => [
                ['label' => 'None', 'value' => 'none'],
                ['label' => 'TLS', 'value' => 'tls'],
                ['label' => 'SSL', 'value' => 'ssl'],
            ],
            self::WEEK_START => [
                ['label' => 'Monday', 'value' => 'monday'],
                ['label' => 'Tuesday', 'value' => 'tuesday'],
                ['label' => 'Wednesday', 'value' => 'wednesday'],
                ['label' => 'Thursday', 'value' => 'thursday'],
                ['label' => 'Friday', 'value' => 'friday'],
                ['label' => 'Saturday', 'value' => 'saturday'],
                ['label' => 'Sunday', 'value' => 'sunday'],
            ],
            default => [],
        };
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
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::SYSTEM_NAME => ['required', 'string', 'max:255'],
            self::TIMEZONE => ['required', 'string', 'timezone'],
            self::DATE_FORMAT => ['required', 'string', 'max:20'],
            self::WEEK_START => ['required', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            self::EXPORT_CLEANUP_DAYS => ['required', 'integer', 'min:1', 'max:365'],
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
            'general' => 'General',
            'mail' => 'Mail',
        ];
    }
}
