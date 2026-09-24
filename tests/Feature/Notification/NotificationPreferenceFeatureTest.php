<?php

use App\Enums\NotificationType;
use App\Enums\UserSettingKey;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from notification preferences', function () {
    $this->get(route('notification-preferences.edit'))->assertRedirect();
});

test('users can view their notification preferences', function () {
    $this->actingAs(member())
        ->get(route('notification-preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/notifications')
            ->has('preferences')
            ->has('types'));
});

test('users can update their notification preferences', function () {
    $user = member();

    $this->actingAs($user)
        ->from(route('notification-preferences.edit'))
        ->patch(route('notification-preferences.update'), [
            'inapp' => false,
            'sound' => false,
            'desktop' => true,
            'muted_types' => [NotificationType::EXPORT_COMPLETED->value],
        ])
        ->assertRedirect(route('notification-preferences.edit'));

    $settings = $user->settings();

    expect($settings->get(UserSettingKey::NOTIFICATION_INAPP_ENABLED->value))->toBe('0')
        ->and($settings->get(UserSettingKey::NOTIFICATION_SOUND_ENABLED->value))->toBe('0')
        ->and($settings->get(UserSettingKey::NOTIFICATION_DESKTOP_ENABLED->value))->toBe('1')
        ->and(json_decode((string) $settings->get(UserSettingKey::NOTIFICATION_MUTED_TYPES->value), true))
        ->toBe([NotificationType::EXPORT_COMPLETED->value]);
});

test('unknown muted types are rejected', function () {
    $this->actingAs(member())
        ->patch(route('notification-preferences.update'), [
            'inapp' => true,
            'sound' => true,
            'desktop' => false,
            'muted_types' => ['not.a.type'],
        ])
        ->assertSessionHasErrors('muted_types.0');
});
