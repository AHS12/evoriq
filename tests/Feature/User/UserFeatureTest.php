<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('lists users for those allowed to view all', function () {
    $this->actingAs(superAdmin())
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data')
            ->has('roles')
            ->has('statuses'));
});

test('forbids members from listing users', function () {
    $this->actingAs(member())
        ->get(route('users.index'))
        ->assertForbidden();
});

test('creates an invited user and sends the invitation', function () {
    Notification::fake();

    $this->actingAs(admin())
        ->post(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'roles' => ['Member'],
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->status)->toBe(UserStatus::INVITED)
        ->and($user->invitation_token)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole(UserRole::MEMBER->value))->toBeTrue();

    Notification::assertSentTo($user, UserInvitation::class);
});

test('validates the store payload', function () {
    $this->actingAs(admin())
        ->post(route('users.store'), ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);
});

test('updates a user and their roles', function () {
    $target = makeUser(UserRole::MEMBER);

    $this->actingAs(admin())
        ->patch(route('users.update', $target), [
            'name' => 'Renamed User',
            'email' => 'renamed@example.com',
            'roles' => ['Admin'],
        ])
        ->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->name)->toBe('Renamed User')
        ->and($target->hasRole(UserRole::ADMIN->value))->toBeTrue();
});

test('only a super admin can assign the super admin role', function () {
    $target = makeUser(UserRole::MEMBER);

    $this->actingAs(admin())
        ->post(route('users.roles', $target), ['roles' => ['Super Admin']])
        ->assertSessionHasErrors('roles');

    expect($target->fresh()->hasRole(UserRole::SUPER_ADMIN->value))->toBeFalse();

    $this->actingAs(superAdmin())
        ->post(route('users.roles', $target), ['roles' => ['Super Admin']])
        ->assertRedirect(route('users.index'));

    expect($target->fresh()->hasRole(UserRole::SUPER_ADMIN->value))->toBeTrue();
});

test('resends the invitation and rotates the token', function () {
    Notification::fake();

    $target = User::factory()->invited()->create(['email' => 'invited@example.com']);
    $original = $target->invitation_token;

    $this->actingAs(admin())
        ->post(route('users.invite', $target))
        ->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->invitation_token)->not->toBe($original);
    Notification::assertSentTo($target, UserInvitation::class);
});

test('suspends and reactivates a user', function () {
    $target = makeUser(UserRole::MEMBER);

    $this->actingAs(admin())
        ->post(route('users.suspend', $target))
        ->assertRedirect(route('users.index'));

    expect($target->fresh()->status)->toBe(UserStatus::SUSPENDED);

    $this->actingAs(admin())
        ->post(route('users.suspend', $target))
        ->assertRedirect(route('users.index'));

    expect($target->fresh()->status)->toBe(UserStatus::ACTIVE);
});

test('an admin cannot delete the super admin', function () {
    $superAdmin = superAdmin();

    $this->actingAs(admin())
        ->delete(route('users.destroy', $superAdmin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
});

test('even a super admin cannot delete themselves', function () {
    $superAdmin = superAdmin();

    $this->actingAs($superAdmin)
        ->delete(route('users.destroy', $superAdmin))
        ->assertForbidden();

    $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
});

test('suspended users are signed out on their next request', function () {
    $user = makeUser(UserRole::MEMBER);
    $user->update(['status' => UserStatus::SUSPENDED]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('the invitation email is queued on the default queue', function () {
    Bus::fake();

    $user = User::factory()->create();
    $user->notify(new UserInvitation('https://example.com/invitations/accept', 7));

    Bus::assertDispatched(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->queue === null || $job->queue === 'default',
    );
});

test('the password reset email is queued on the default queue', function () {
    Bus::fake();

    $target = makeUser(UserRole::MEMBER);

    $this->actingAs(admin())
        ->post(route('users.password-reset', $target))
        ->assertRedirect(route('users.index'));

    Bus::assertDispatched(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->queue === null || $job->queue === 'default',
    );
});
