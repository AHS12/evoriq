<?php

use App\Enums\AuditEvent;
use App\Models\AuditActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;

test('a real login is recorded on the auth channel with the IP', function () {
    $user = User::factory()->create();

    auth()->login($user);

    $row = AuditActivity::query()->where('event', AuditEvent::LOGIN->value)->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('auth')
        ->and($row->causer_id)->toBe($user->id)
        ->and($row->subject_id)->toBe($user->id);
});

test('a logout is recorded on the auth channel', function () {
    $user = User::factory()->create();

    auth()->login($user);
    auth()->logout();

    $row = AuditActivity::query()->where('event', AuditEvent::LOGOUT->value)->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('auth')
        ->and($row->causer_id)->toBe($user->id);
});

test('a failed login attempt is recorded with the attempted email', function () {
    event(new Failed('web', null, ['email' => 'intruder@example.test']));

    $row = AuditActivity::query()->where('event', AuditEvent::LOGIN_FAILED->value)->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('security')
        ->and($row->causer_id)->toBeNull()
        ->and($row->properties->get('attempted_email'))->toBe('intruder@example.test');
});

test('a lockout is recorded on the security channel', function () {
    $request = Request::create('/login', 'POST', ['email' => 'intruder@example.test']);

    event(new Lockout($request));

    $row = AuditActivity::query()->where('event', AuditEvent::LOCKOUT->value)->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('security')
        ->and($row->properties->get('attempted_email'))->toBe('intruder@example.test');
});

test('a password reset is recorded on the security channel', function () {
    $user = User::factory()->create();

    event(new PasswordReset($user));

    $row = AuditActivity::query()->where('event', AuditEvent::PASSWORD_RESET->value)->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('security')
        ->and($row->causer_id)->toBe($user->id);
});

test('a verified email is recorded on the security channel', function () {
    $user = User::factory()->create();

    event(new Verified($user));

    $row = AuditActivity::query()->where('event', AuditEvent::EMAIL_VERIFIED->value)->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('security')
        ->and($row->causer_id)->toBe($user->id);
});
