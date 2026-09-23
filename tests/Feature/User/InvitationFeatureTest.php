<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function signedInvitationUrl(User $user, ?string $token = null, ?int $expiresInMinutes = null): string
{
    return URL::temporarySignedRoute(
        'invitations.accept',
        $expiresInMinutes === null ? now()->addDay() : now()->addMinutes($expiresInMinutes),
        ['user' => $user->id, 'token' => $token ?? $user->invitation_token],
    );
}

test('shows the accept invitation form for a valid link', function () {
    $user = User::factory()->invited()->create();

    $this->get(signedInvitationUrl($user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('user.email', $user->email));
});

test('accepts the invitation, sets the password and signs the user in', function () {
    $user = User::factory()->invited()->create(['email' => 'invite@example.com']);

    $this->post(signedInvitationUrl($user), [
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->status)->toBe(UserStatus::ACTIVE)
        ->and($user->invitation_token)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Password123!', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

test('validates the chosen password', function () {
    $user = User::factory()->invited()->create();

    $this->post(signedInvitationUrl($user), [
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');
});

test('rejects a link invalidated by a newer invitation', function () {
    $user = User::factory()->invited()->create();
    $url = signedInvitationUrl($user);

    $user->update(['invitation_token' => 'rotated-token']);

    $this->get($url)->assertRedirect(route('login'));
});

test('rejects an expired invitation link', function () {
    $user = User::factory()->invited()->create();

    $this->get(signedInvitationUrl($user, expiresInMinutes: -1))
        ->assertForbidden();
});

test('rejects an invitation for an already active user', function () {
    $user = User::factory()->create();

    $this->get(signedInvitationUrl($user, token: 'stale-token'))
        ->assertRedirect(route('login'));
});
