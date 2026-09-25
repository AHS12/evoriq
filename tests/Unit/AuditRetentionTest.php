<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\AuditLogName;
use App\Enums\SettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('retention days come from the stored per-channel setting', function () {
    Settings::set(SettingKey::AUDIT_RETENTION_DOMAIN->value, '1825');

    expect(AuditLogName::DOMAIN->retentionDays())->toBe(1825)
        ->and(AuditLogName::SECURITY->retentionDays())->toBe(365);
});

test('retention days fall back to the setting default when nothing is stored', function () {
    Settings::forget(SettingKey::AUDIT_RETENTION_DOMAIN->value);

    expect(AuditLogName::DOMAIN->retentionDays())->toBe(SettingKey::AUDIT_RETENTION_DOMAIN->defaultValue());
});

test('retention days never drop below one', function () {
    Settings::set(SettingKey::AUDIT_RETENTION_DOMAIN->value, '-5');

    expect(AuditLogName::DOMAIN->retentionDays())->toBe(1);
});
