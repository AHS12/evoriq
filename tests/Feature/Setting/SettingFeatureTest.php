<?php

use Ahs12\Setanjo\Facades\Settings;
use App\Enums\SettingKey;
use App\Mail\TestMail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

it('shows the general settings page to authorized users', function () {
    $user = makeUserWithPermissions(['settings.view']);

    $this->actingAs($user)
        ->get(route('admin.settings.general.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/general')
            ->has('group.fields'));
});

it('forbids users without the settings permission', function () {
    $user = makeUser();

    $this->actingAs($user)
        ->get(route('admin.settings.general.edit'))
        ->assertForbidden();
});

it('updates general settings', function () {
    $user = makeUserWithPermissions(['settings.view', 'settings.update']);

    $this->actingAs($user)
        ->patch(route('admin.settings.general.update'), [
            'system_name' => 'Acme',
            'timezone' => 'Europe/London',
            'date_format' => 'd/m/Y',
            'week_start' => 'sunday',
            'export_cleanup_days' => 14,
        ])
        ->assertRedirect(route('admin.settings.general.edit'));

    expect(Settings::get('system_name'))->toBe('Acme')
        ->and(Settings::get('export_cleanup_days'))->toBe(14);
});

it('validates general settings', function () {
    $user = makeUserWithPermissions(['settings.view', 'settings.update']);

    $this->actingAs($user)
        ->patch(route('admin.settings.general.update'), [
            'system_name' => '',
            'timezone' => 'Nope/Nowhere',
            'date_format' => '',
            'week_start' => 'noday',
            'export_cleanup_days' => 0,
        ])
        ->assertSessionHasErrors([
            'system_name',
            'timezone',
            'date_format',
            'week_start',
            'export_cleanup_days',
        ]);
});

it('accepts any day as the week start', function () {
    $user = makeUserWithPermissions(['settings.view', 'settings.update']);

    $this->actingAs($user)
        ->patch(route('admin.settings.general.update'), [
            'system_name' => 'Acme',
            'timezone' => 'Europe/London',
            'date_format' => 'Y-m-d',
            'week_start' => 'friday',
            'export_cleanup_days' => 7,
        ])
        ->assertSessionHasNoErrors();

    expect(Settings::get('week_start'))->toBe('friday');
});

it('encrypts the mail password at rest', function () {
    $user = makeUserWithPermissions(['settings.view', 'settings.update']);

    $this->actingAs($user)
        ->patch(route('admin.settings.mail.update'), [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_username' => 'user',
            'mail_password' => 'secret-pass',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'hello@example.com',
            'mail_from_name' => 'Acme',
        ])
        ->assertRedirect(route('admin.settings.mail.edit'));

    $stored = Settings::get(SettingKey::MAIL_PASSWORD->value);

    expect($stored)->not->toBe('secret-pass')
        ->and(Crypt::decryptString($stored))->toBe('secret-pass');
});

it('queues a test email', function () {
    Mail::fake();

    $user = makeUserWithPermissions(['settings.view', 'settings.update']);

    $this->actingAs($user)
        ->post(route('admin.settings.mail.test'))
        ->assertRedirect();

    Mail::assertQueued(TestMail::class, fn ($mail) => $mail->hasTo($user->email));
});
