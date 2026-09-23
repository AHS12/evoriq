<?php

use App\DTOs\Setting\AppearanceDTO;
use App\Enums\AppearanceContrast;
use App\Enums\AppearanceMode;
use App\Enums\AppearanceTheme;
use App\Enums\UserSettingKey;
use App\Models\User;
use App\Services\Setting\AppearanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AppearanceService;
});

test('forUser returns defaults when nothing is stored', function () {
    $user = User::factory()->create();

    expect($this->service->forUser($user))->toBe([
        'mode' => 'system',
        'theme' => 'default',
        'accent' => '#3b82f6',
        'contrast' => 'normal',
    ]);
});

test('forUser merges stored preferences over the defaults', function () {
    $user = User::factory()->create();
    $user->settings()->set(UserSettingKey::APPEARANCE_MODE->value, 'dark');
    $user->settings()->set(UserSettingKey::APPEARANCE_THEME->value, 'dracula');
    $user->settings()->set(UserSettingKey::APPEARANCE_ACCENT->value, '#7c3aed');

    expect($this->service->forUser($user))->toBe([
        'mode' => 'dark',
        'theme' => 'dracula',
        'accent' => '#7c3aed',
        'contrast' => 'normal',
    ]);
});

test('stored only returns the explicitly set axes', function () {
    $user = User::factory()->create();
    $user->settings()->set(UserSettingKey::APPEARANCE_MODE->value, 'dark');
    $user->settings()->set(UserSettingKey::APPEARANCE_THEME->value, 'khaki');

    expect($this->service->stored($user))->toBe([
        'mode' => 'dark',
        'theme' => 'khaki',
    ]);
});

test('stored ignores unknown values', function () {
    $user = User::factory()->create();
    $user->settings()->set(UserSettingKey::APPEARANCE_MODE->value, 'neon');
    $user->settings()->set(UserSettingKey::APPEARANCE_ACCENT->value, 'not-a-color');

    expect($this->service->stored($user))->toBe([]);
});

test('update persists the mapped preferences', function () {
    $user = User::factory()->create();

    $this->service->update($user, new AppearanceDTO(
        AppearanceMode::DARK,
        AppearanceTheme::KHAKI,
        '#10b981',
        AppearanceContrast::HIGH,
    ));

    $settings = $user->settings();

    expect($settings->get(UserSettingKey::APPEARANCE_MODE->value))->toBe('dark')
        ->and($settings->get(UserSettingKey::APPEARANCE_THEME->value))->toBe('khaki')
        ->and($settings->get(UserSettingKey::APPEARANCE_ACCENT->value))->toBe('#10b981')
        ->and($settings->get(UserSettingKey::APPEARANCE_CONTRAST->value))->toBe('high');
});
