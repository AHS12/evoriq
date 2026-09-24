<?php

use App\DTOs\Notification\NotificationFeedFilterDTO;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(NotificationRepositoryInterface::class);
    $this->filters = new NotificationFeedFilterDTO;
});

test('the feed only returns addressable notifications', function () {
    $user = member();
    $other = member();

    $forUser = Notification::factory()->forUser($user->id)->create();
    $forAll = Notification::factory()->all()->create();
    $forOther = Notification::factory()->forUser($other->id)->create();

    $ids = $this->repository->feedFor($user->id, $this->filters)->pluck('id')->all();

    expect($ids)->toContain($forUser->id, $forAll->id)
        ->not->toContain($forOther->id);
});

test('role targets are addressable', function () {
    $user = member();
    $roleId = $user->roles()->first()->id;

    $notification = Notification::factory()->forRole($roleId)->create();

    expect($this->repository->feedFor($user->id, $this->filters)->pluck('id')->all())
        ->toContain($notification->id);
});

test('expired notifications are excluded', function () {
    $user = member();

    $expired = Notification::factory()->forUser($user->id)->expired()->create();

    expect($this->repository->feedFor($user->id, $this->filters)->pluck('id')->all())
        ->not->toContain($expired->id);
});

test('unread count and mark as read are idempotent', function () {
    $user = member();
    $notification = Notification::factory()->forUser($user->id)->create();

    expect($this->repository->unreadCountFor($user->id))->toBe(1)
        ->and($this->repository->markRead($notification->id, $user->id))->toBeTrue()
        ->and($this->repository->markRead($notification->id, $user->id))->toBeFalse()
        ->and($this->repository->unreadCountFor($user->id))->toBe(0);
});

test('dismiss hides the notification from the feed and unread count', function () {
    $user = member();
    $notification = Notification::factory()->forUser($user->id)->create();

    $this->repository->dismiss($notification->id, $user->id);

    expect($this->repository->unreadCountFor($user->id))->toBe(0)
        ->and($this->repository->feedFor($user->id, $this->filters)->total())->toBe(0);
});

test('mark all read clears every unread notification', function () {
    $user = member();
    Notification::factory()->forUser($user->id)->count(3)->create();
    Notification::factory()->all()->create();

    $this->repository->markAllRead($user->id, now());

    expect($this->repository->unreadCountFor($user->id))->toBe(0);
});

test('isAddressable reflects targeting', function () {
    $user = member();
    $notification = Notification::factory()->all()->create();

    expect($this->repository->isAddressable($notification->id, $user->id))->toBeTrue()
        ->and($this->repository->isAddressable($notification->id, 999999))->toBeTrue();
});

test('pruneExpired deletes old notifications and their children', function () {
    $user = member();

    $old = Notification::factory()->forUser($user->id)->create([
        'created_at' => now()->subDays(120),
    ]);

    $fresh = Notification::factory()->all()->create();

    $deleted = $this->repository->pruneExpired(90);

    expect($deleted)->toBe(1)
        ->and(Notification::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(Notification::query()->whereKey($fresh->id)->exists())->toBeTrue();
});
