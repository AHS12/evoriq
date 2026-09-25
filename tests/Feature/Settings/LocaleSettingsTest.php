<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Enums\UserSettingKey;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can switch the locale and it is kept in the session', function () {
    $this->from(route('home'))
        ->post(route('locale.update'), ['locale' => 'bn'])
        ->assertRedirect(route('home'))
        ->assertSessionHas('locale', 'bn');
});

test('the locale endpoint rejects unknown locales', function () {
    $this->post(route('locale.update'), ['locale' => 'xx'])
        ->assertSessionHasErrors(['locale']);
});

test('authenticated users persist their locale preference', function () {
    $user = member();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('locale.update'), ['locale' => 'fr'])
        ->assertRedirect(route('dashboard'));

    expect($user->settings()->get(UserSettingKey::LOCALE->value))->toBe('fr');
});

test('the middleware applies the user locale to the request', function () {
    $user = member();
    $user->settings()->set(UserSettingKey::LOCALE->value, 'de');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('i18n.locale')->where('i18n.locale', 'de'));
});

test('the session locale wins for guests', function () {
    $this->post(route('locale.update'), ['locale' => 'es']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('i18n.locale')->where('i18n.locale', 'es'));
});

test('the global setting is the default for guests without a session', function () {
    Settings::set(SettingKey::APP_LOCALE->value, 'fr');

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('i18n.locale')->where('i18n.locale', 'fr'));
});

test('the shared payload carries the dictionary and supported locales', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.translations', fn ($translations) => $translations->get('Language updated.') === 'Language updated.')
            ->has('i18n.supported', 5)
            ->has('i18n.supported.0', fn (Assert $locale) => $locale
                ->where('code', 'en')
                ->where('name', 'English')));
});

test('the dictionary is delivered in the active locale', function () {
    $this->post(route('locale.update'), ['locale' => 'bn']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.translations', fn ($translations) => $translations->get('Users') === 'ইউজার'));
});
