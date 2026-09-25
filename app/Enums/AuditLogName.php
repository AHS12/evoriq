<?php

namespace App\Enums;

use Ahs12\Setanjo\Facades\Settings;
use Throwable;

enum AuditLogName: string
{
    case AUTH = 'auth';
    case SECURITY = 'security';
    case RBAC = 'rbac';
    case SETTINGS = 'settings';
    case DOMAIN = 'domain';

    public function label(): string
    {
        return match ($this) {
            self::AUTH => 'Authentication',
            self::SECURITY => 'Security',
            self::RBAC => 'Roles & Permissions',
            self::SETTINGS => 'Settings',
            self::DOMAIN => 'Domain',
        };
    }

    /**
     * The number of days entries in this log are retained before pruning.
     *
     * Resolution order: the per-channel setting stored in the "Audit log"
     * settings section, then the SettingKey default, then the
     * `audit.retention` config map (pre-install/console fallback).
     */
    public function retentionDays(): int
    {
        $key = $this->retentionSetting();

        try {
            $stored = Settings::get($key->value, $key->defaultValue());
        } catch (Throwable) {
            $stored = null;
        }

        $days = (int) ($stored ?? config("audit.retention.{$this->value}", 90));

        return max(1, $days);
    }

    private function retentionSetting(): SettingKey
    {
        return match ($this) {
            self::AUTH => SettingKey::AUDIT_RETENTION_AUTH,
            self::SECURITY => SettingKey::AUDIT_RETENTION_SECURITY,
            self::RBAC => SettingKey::AUDIT_RETENTION_RBAC,
            self::SETTINGS => SettingKey::AUDIT_RETENTION_SETTINGS,
            self::DOMAIN => SettingKey::AUDIT_RETENTION_DOMAIN,
        };
    }
}
