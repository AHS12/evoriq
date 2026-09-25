<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Enums\UserSettingKey;
use App\Models\User;
use App\Services\Setting\LocaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LocaleService;
});

test('storedForUser returns null for guests and unset users', function () {
    expect($this->service->storedForUser(null))->toBeNull()
        ->and($this->service->storedForUser(User::factory()->create()))->toBeNull();
});

test('storedForUser returns the stored locale', function () {
    $user = User::factory()->create();
    $user->settings()->set(UserSettingKey::LOCALE->value, 'bn');

    expect($this->service->storedForUser($user))->toBe('bn');
});

test('storedForUser ignores unknown locales', function () {
    $user = User::factory()->create();
    $user->settings()->set(UserSettingKey::LOCALE->value, 'xx');

    expect($this->service->storedForUser($user))->toBeNull();
});

test('update persists the user locale', function () {
    $user = User::factory()->create();

    $this->service->update($user, 'fr');

    expect($user->settings()->get(UserSettingKey::LOCALE->value))->toBe('fr');
});

test('default returns the global setting', function () {
    Settings::set(SettingKey::APP_LOCALE->value, 'de');

    expect($this->service->default())->toBe('de');
});

test('default falls back to the configured locale for unknown values', function () {
    Settings::set(SettingKey::APP_LOCALE->value, 'xx');

    expect($this->service->default())->toBe(config('app.locale'));
});

test('dictionary returns translations for the requested locale', function () {
    $dictionary = $this->service->dictionary('bn');

    expect($dictionary)->toHaveKey('Language updated.')
        ->and($dictionary['Language updated.'])->toBe('ভাষা হালনাগাদ হয়েছে।')
        ->and($dictionary)->toHaveCount(count($this->service->dictionary('en')));
});

test('dictionary returns the fallback locale unchanged', function () {
    expect($this->service->dictionary('en'))->toHaveKey('Language updated.', 'Language updated.')
        ->and($this->service->dictionary('en'))->not->toBeEmpty();
});
