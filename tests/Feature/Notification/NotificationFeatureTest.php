<?php

use App\DTOs\Notification\NotificationFeedFilterDTO;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from the notifications page', function () {
    $this->get(route('notifications.index'))->assertRedirect();
});

test('the feed only contains addressable notifications', function () {
    $user = member();
    $other = member();

    Notification::factory()->forUser($user->id)->create(['title' => 'For me']);
    Notification::factory()->all()->create(['title' => 'For everyone']);
    Notification::factory()->forUser($other->id)->create(['title' => 'For someone else']);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('feed.data', 2)
            ->has('filters')
            ->has('priorities'));
});

test('the shared notifications prop is present', function () {
    $this->actingAs(member())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('notifications.unread_count')
            ->has('notifications.recent')
            ->has('notifications.preferences'));
});

test('a user can mark a notification as read', function () {
    $user = member();
    $notification = Notification::factory()->forUser($user->id)->create();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification))
        ->assertRedirect();

    $this->assertDatabaseHas('notification_reads', [
        'notification_id' => $notification->id,
        'user_id' => $user->id,
    ]);
});

test('marking a non-addressable notification returns not found', function () {
    $user = member();
    $other = member();
    $notification = Notification::factory()->forUser($other->id)->create();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification))
        ->assertNotFound();
});

test('a user can mark all notifications as read', function () {
    $user = member();
    Notification::factory()->forUser($user->id)->create();
    Notification::factory()->all()->create();

    $this->actingAs($user)
        ->post(route('notifications.read-all'))
        ->assertRedirect();

    expect(app(NotificationRepositoryInterface::class)->unreadCountFor($user->id))->toBe(0);
});

test('a user can dismiss their notification', function () {
    $user = member();
    $notification = Notification::factory()->forUser($user->id)->create();

    $this->actingAs($user)
        ->delete(route('notifications.destroy', $notification))
        ->assertRedirect();

    $this->assertDatabaseHas('notification_reads', [
        'notification_id' => $notification->id,
        'user_id' => $user->id,
    ]);

    expect(app(NotificationRepositoryInterface::class)->feedFor($user->id, new NotificationFeedFilterDTO)->total())
        ->toBe(0);
});
