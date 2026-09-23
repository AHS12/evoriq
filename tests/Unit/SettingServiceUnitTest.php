<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Services\Setting\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('groups returns the configurable setting groups', function () {
    $groups = app(SettingService::class)->groups();

    expect(array_column($groups, 'key'))->toBe(['general', 'mail']);
});

test('update encrypts secret values', function () {
    $service = app(SettingService::class);

    $service->update([SettingKey::MAIL_PASSWORD->value => 'first']);

    $stored = Settings::get(SettingKey::MAIL_PASSWORD->value);

    expect($stored)->not->toBe('first')
        ->and(Crypt::decryptString($stored))->toBe('first');
});

test('update keeps the existing secret when a blank value is submitted', function () {
    $service = app(SettingService::class);

    $service->update([SettingKey::MAIL_PASSWORD->value => 'first']);
    $service->update([SettingKey::MAIL_PASSWORD->value => '']);

    expect($service->get(SettingKey::MAIL_PASSWORD))->toBe('first');
});
