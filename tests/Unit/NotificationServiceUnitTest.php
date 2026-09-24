<?php

use App\DTOs\Notification\NotificationDTO;
use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Enums\SettingKey;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\Notification\NotificationService;
use App\Services\Setting\SettingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->enabled = true;

    $this->repository = Mockery::mock(NotificationRepositoryInterface::class);
    $this->settings = Mockery::mock(SettingService::class);
    $this->settings->shouldReceive('get')
        ->with(SettingKey::NOTIFICATION_ENABLED)
        ->andReturnUsing(fn (): bool => $this->enabled);

    $this->service = new NotificationService($this->repository, $this->settings);
});

afterEach(function () {
    Mockery::close();
});

test('create persists the notification and its targets', function () {
    $notification = Notification::factory()->create([
        'type' => NotificationType::SYSTEM_ANNOUNCEMENT->value,
        'priority' => NotificationPriority::INFO,
    ]);

    $this->repository->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['type'] === NotificationType::SYSTEM_ANNOUNCEMENT->value))
        ->andReturn($notification);

    $this->repository->shouldReceive('createTargets')
        ->once()
        ->with($notification, Mockery::on(fn (array $targets): bool => $targets === [
            ['target_type' => 'all', 'target_id' => null],
        ]));

    $dto = new NotificationDTO(type: NotificationType::SYSTEM_ANNOUNCEMENT, title: 'Hello');

    expect($this->service->create($dto, 7))->toBe($notification);
});

test('create is a no-op when notifications are disabled', function () {
    $this->enabled = false;

    $this->repository->shouldNotReceive('create');
    $this->repository->shouldNotReceive('createTargets');

    expect($this->service->create(new NotificationDTO(type: NotificationType::SYSTEM_ANNOUNCEMENT, title: 'X')))
        ->toBeNull();
});

test('markRead throws when the notification is not addressable', function () {
    $notification = Notification::factory()->create();

    $this->repository->shouldReceive('isAddressable')
        ->once()
        ->with($notification->id, 5)
        ->andReturnFalse();

    expect(fn () => $this->service->markRead($notification, 5))
        ->toThrow(ModelNotFoundException::class);
});

test('markRead marks when addressable', function () {
    $notification = Notification::factory()->create();

    $this->repository->shouldReceive('isAddressable')->with($notification->id, 5)->andReturnTrue();
    $this->repository->shouldReceive('markRead')->once()->with($notification->id, 5)->andReturnTrue();

    $this->service->markRead($notification, 5);
});

test('dismiss guards addressability before deleting', function () {
    $notification = Notification::factory()->create();

    $this->repository->shouldReceive('isAddressable')->with($notification->id, 5)->andReturnFalse();

    expect(fn () => $this->service->dismiss($notification, 5))
        ->toThrow(ModelNotFoundException::class);
});

test('markAllRead delegates with the current timestamp', function () {
    $this->repository->shouldReceive('markAllRead')
        ->once()
        ->with(5, Mockery::on(fn ($timestamp): bool => $timestamp instanceof DateTimeInterface))
        ->andReturn(3);

    $this->service->markAllRead(5);
});

test('pruneExpired delegates with the configured chunk size', function () {
    config(['notification.retention.chunk' => 250]);

    $this->repository->shouldReceive('pruneExpired')->once()->with(30, 250)->andReturn(3);

    expect($this->service->pruneExpired(30))->toBe(3);
});
