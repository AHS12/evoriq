<?php

use App\DTOs\Setting\NotificationPreferenceDTO;
use App\Enums\NotificationType;
use App\Enums\UserSettingKey;
use App\Services\Audit\AuditLogService;
use App\Services\Setting\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();
    $this->service = new NotificationPreferenceService($this->audit);
});

afterEach(function () {
    Mockery::close();
});

test('defaults are returned when nothing is stored', function () {
    expect($this->service->forUser(member()))->toBe([
        'inapp' => true,
        'sound' => true,
        'desktop' => false,
        'muted_types' => [],
        'muted_categories' => [],
    ]);
});

test('defaults are returned for a guest', function () {
    expect($this->service->forUser(null))->toBe($this->service->defaults());
});

test('update persists the preferences and they are merged on read', function () {
    $user = member();

    $this->service->update($user, new NotificationPreferenceDTO(
        inapp: false,
        sound: false,
        desktop: true,
        mutedTypes: [NotificationType::EXPORT_COMPLETED->value],
    ));

    expect($this->service->forUser($user))->toBe([
        'inapp' => false,
        'sound' => false,
        'desktop' => true,
        'muted_types' => [NotificationType::EXPORT_COMPLETED->value],
        'muted_categories' => [],
    ]);
});

test('a muted category suppresses notifications of that category', function () {
    $user = member();

    $this->service->update($user, new NotificationPreferenceDTO(
        mutedCategories: ['pipeline'],
    ));

    expect($this->service->allows($user, NotificationType::JOB_QUEUED))->toBeFalse()
        ->and($this->service->allows($user, NotificationType::USER_INVITED))->toBeTrue();
});

test('stored muted types drop unknown values', function () {
    $user = member();

    $user->settings()->set(
        UserSettingKey::NOTIFICATION_MUTED_TYPES->value,
        json_encode([NotificationType::EXPORT_COMPLETED->value, 'not.a.type']),
    );

    expect($this->service->forUser($user)['muted_types'])
        ->toBe([NotificationType::EXPORT_COMPLETED->value]);
});
