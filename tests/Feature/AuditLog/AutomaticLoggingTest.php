<?php

use App\DTOs\Setting\NotificationPreferenceDTO;
use App\Enums\AuditEvent;
use App\Enums\UserStatus;
use App\Models\AuditActivity;
use App\Models\Upload;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Profile\ProfileService;
use App\Services\Setting\NotificationPreferenceService;
use App\Services\User\UserService;
use Illuminate\Http\UploadedFile;

/**
 * The freshest audit row matching a description fragment.
 */
function latestAuditRow(?string $description = null): AuditActivity
{
    return AuditActivity::query()
        ->when($description !== null, fn ($query) => $query->where('description', $description))
        ->latest('id')
        ->firstOrFail();
}

test('updating an audited model writes one dirty-only row', function () {
    $user = User::factory()->create(['name' => 'Before']);

    $before = AuditActivity::query()->count();

    $user->update(['name' => 'After']);

    $row = latestAuditRow('User updated');

    expect(AuditActivity::query()->count())->toBe($before + 1)
        ->and($row->log_name)->toBe('security')
        ->and($row->event)->toBe('updated')
        ->and($row->attribute_changes->get('attributes'))->toBe(['name' => 'After'])
        ->and($row->attribute_changes->get('old'))->toBe(['name' => 'Before']);
});

test('a save that only bumps audit-ignored columns writes no row', function () {
    $user = User::factory()->create();

    $before = AuditActivity::query()->where('event', 'updated')->count();

    $user->update(['updated_by' => $user->id]);

    expect(AuditActivity::query()->where('event', 'updated')->count())->toBe($before);
});

test('a no-op save writes no row', function () {
    $user = User::factory()->create();

    $before = AuditActivity::query()->where('event', 'updated')->count();

    $user->update(['name' => $user->name]);

    expect(AuditActivity::query()->where('event', 'updated')->count())->toBe($before);
});

test('password changes never reach the attribute diff', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'a-brand-new-secret-password']);

    $row = latestAuditRow('User updated');

    $changes = json_encode($row->attribute_changes);

    expect($changes)->not->toContain('a-brand-new-secret-password')
        ->and($row->attribute_changes->get('attributes'))->not->toHaveKey('password');
});

test('upload lifecycle is audited on the domain channel', function () {
    $upload = Upload::factory()->create(['name' => 'Contract']);

    $created = latestAuditRow('Upload created');
    expect($created->log_name)->toBe('domain')
        ->and($created->subject_id)->toBe($upload->id);

    $upload->update(['name' => 'Contract v2']);

    $updated = latestAuditRow('Upload updated');
    expect($updated->attribute_changes->get('attributes'))->toBe(['name' => 'Contract v2']);
});

test('suspending a user records the named security event', function () {
    $actor = admin();
    $user = User::factory()->create();

    $this->actingAs($actor);

    app(UserService::class)->updateStatus($user, UserStatus::SUSPENDED);

    $row = latestAuditRow('User suspended');

    expect($row->log_name)->toBe('security')
        ->and($row->event)->toBe('user_suspended')
        ->and($row->causer_id)->toBe($actor->id)
        ->and($row->subject_id)->toBe($user->id);
});

test('rows written during one request share a correlation id', function () {
    $user = User::factory()->create();

    $this->get('/')->assertOk();

    $service = app(AuditLogService::class);
    $service->record(AuditEvent::LOGIN, $user);
    $service->record(AuditEvent::LOGOUT, $user);

    $rows = AuditActivity::query()
        ->where('event', AuditEvent::LOGIN->value)
        ->orWhere('event', AuditEvent::LOGOUT->value)
        ->orderBy('id', 'desc')
        ->limit(2)
        ->get();

    expect($rows)->toHaveCount(2);

    $correlationIds = $rows->pluck('properties.correlation_id')->unique();

    expect($correlationIds->count())->toBe(1)
        ->and($rows->first()?->properties->get('ip_address'))->not->toBeNull();
});

test('audit rows written via the service carry request context', function () {
    $user = admin();

    $this->actingAs($user);

    $this->get('/dashboard')->assertOk();

    app(AuditLogService::class)->record(AuditEvent::LOGIN_FAILED, $user);

    $row = latestAuditRow('Failed login');

    expect($row->properties->get('correlation_id'))->not->toBeNull()
        ->and($row->properties->get('ip_address'))->not->toBeNull();
});

test('avatar upload and removal are audited on the security channel', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    app(ProfileService::class)->updateAvatar($user, UploadedFile::fake()->image('me.png'));

    $updated = latestAuditRow('Avatar updated');
    expect($updated->log_name)->toBe('security')
        ->and($updated->causer_id)->toBe($user->id);

    app(ProfileService::class)->removeAvatar($user);

    $removed = latestAuditRow('Avatar removed');
    expect($removed->event)->toBe(AuditEvent::AVATAR_REMOVED->value);
});

test('notification preference changes are audited on the settings channel', function () {
    $user = admin();

    $this->actingAs($user);

    app(NotificationPreferenceService::class)->update(
        $user,
        new NotificationPreferenceDTO(inapp: false, sound: true, desktop: true, mutedTypes: []),
    );

    $row = latestAuditRow('Notification preferences updated');

    expect($row->log_name)->toBe('settings')
        ->and($row->causer_id)->toBe($user->id)
        ->and($row->properties->get('inapp'))->toBeFalse();
});
