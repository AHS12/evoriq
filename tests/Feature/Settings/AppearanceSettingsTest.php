<?php

use Ahs12\Setanjo\Models\Setting;
use App\Enums\AppearanceTheme;
use App\Enums\UserSettingKey;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from appearance settings', function () {
    $this->get(route('admin.settings.appearance.edit'))->assertRedirect();
});

test('users with permission can view the appearance page', function () {
    $this->actingAs(makeUserWithPermissions(['settings.view']))
        ->get(route('admin.settings.appearance.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/settings/appearance'));
});

test('users without permission cannot view the appearance page', function () {
    $this->actingAs(member())
        ->get(route('admin.settings.appearance.edit'))
        ->assertForbidden();
});

test('the server renders the stored appearance on the root element', function () {
    $user = makeUserWithPermissions(['settings.view']);

    $settings = $user->settings();
    $settings->set(UserSettingKey::APPEARANCE_MODE->value, 'dark');
    $settings->set(UserSettingKey::APPEARANCE_THEME->value, 'dracula');
    $settings->set(UserSettingKey::APPEARANCE_ACCENT->value, '#e11d48');
    $settings->set(UserSettingKey::APPEARANCE_CONTRAST->value, 'high');

    $this->actingAs($user)
        ->get(route('admin.settings.appearance.edit'))
        ->assertOk()
        ->assertSee('class="dark"', false)
        ->assertSee('data-theme="dracula"', false)
        ->assertSee('data-accent="#e11d48"', false)
        ->assertSee('--primary: #e11d48', false)
        ->assertSee('data-contrast="high"', false);
});

test('the cookie supplies appearance for guests', function () {
    $this->withUnencryptedCookie('appearance', 'mode=dark&theme=khaki&accent=%23f59e0b&contrast=high')
        ->get(route('login'))
        ->assertOk()
        ->assertSee('data-theme="khaki"', false)
        ->assertSee('data-accent="#f59e0b"', false);
});

test('users can update their appearance preferences', function () {
    $user = member();

    $this->actingAs($user)
        ->from(route('admin.settings.appearance.edit'))
        ->patch(route('appearance.update'), [
            'mode' => 'dark',
            'theme' => 'dracula',
            'accent' => '#8b5cf6',
            'contrast' => 'high',
        ])
        ->assertRedirect(route('admin.settings.appearance.edit'));

    $settings = $user->settings();

    expect($settings->get(UserSettingKey::APPEARANCE_MODE->value))->toBe('dark')
        ->and($settings->get(UserSettingKey::APPEARANCE_THEME->value))->toBe('dracula')
        ->and($settings->get(UserSettingKey::APPEARANCE_ACCENT->value))->toBe('#8b5cf6')
        ->and($settings->get(UserSettingKey::APPEARANCE_CONTRAST->value))->toBe('high');
});

test('appearance preferences are stored per user', function () {
    $first = member();
    $second = member();

    $this->actingAs($first)->patch(route('appearance.update'), [
        'mode' => 'light',
        'theme' => 'khaki',
        'accent' => '#f59e0b',
        'contrast' => 'normal',
    ]);

    $this->actingAs($second)->patch(route('appearance.update'), [
        'mode' => 'dark',
        'theme' => 'default',
        'accent' => '#64748b',
        'contrast' => 'high',
    ]);

    expect($first->settings()->get(UserSettingKey::APPEARANCE_THEME->value))
        ->toBe(AppearanceTheme::KHAKI->value)
        ->and($second->settings()->get(UserSettingKey::APPEARANCE_THEME->value))
        ->toBe(AppearanceTheme::DEFAULT->value);
});

test('appearance settings are removed when the user is deleted', function () {
    $user = member();
    $userId = $user->id;

    $user->settings()->set(UserSettingKey::APPEARANCE_THEME->value, 'khaki');

    expect(Setting::query()->where('tenantable_id', $userId)->count())->toBe(1);

    $user->delete();

    expect(Setting::query()->where('tenantable_id', $userId)->count())->toBe(0);
});

test('appearance validation rejects unknown values', function () {
    $this->actingAs(member())
        ->patch(route('appearance.update'), [
            'mode' => 'neon',
            'theme' => 'nope',
            'accent' => 'not-a-color',
            'contrast' => 'nope',
        ])
        ->assertSessionHasErrors(['mode', 'theme', 'accent', 'contrast']);
});
