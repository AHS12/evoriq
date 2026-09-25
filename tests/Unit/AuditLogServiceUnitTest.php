<?php

use App\DTOs\AuditLog\AuditLogFilterDTO;
use App\Enums\AuditEvent;
use App\Enums\AuditLogName;
use App\Models\AuditActivity;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(AuditLogRepositoryInterface::class);
    $this->service = new AuditLogService($this->repository);
});

afterEach(function () {
    Mockery::close();
});

test('record writes a named event row with actor, subject and channel', function () {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    $this->service->record(
        AuditEvent::USER_SUSPENDED,
        $subject,
        ['from' => 'active', 'to' => 'suspended'],
        $actor,
    );

    $row = AuditActivity::query()->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('domain')
        ->and($row->event)->toBe('user_suspended')
        ->and($row->description)->toBe('User suspended')
        ->and($row->causer_id)->toBe($actor->id)
        ->and($row->subject_id)->toBe($subject->id)
        ->and($row->properties->get('from'))->toBe('active');
});

test('record does not write when the collector is disabled', function () {
    config()->set('activitylog.enabled', false);

    $before = AuditActivity::query()->count();

    $this->service->record(AuditEvent::LOGIN);

    expect(AuditActivity::query()->count())->toBe($before);
});

test('record redacts sensitive property keys before persisting', function () {
    $this->service->record(AuditEvent::SETTING_UPDATED, properties: [
        'key' => 'smtp_password',
        'password' => 'super-secret',
        'note' => 'kept',
    ]);

    $row = AuditActivity::query()->latest('id')->firstOrFail();

    expect($row->properties->get('password'))->toBe('[REDACTED]')
        ->and($row->properties->get('key'))->toBe('smtp_password')
        ->and($row->properties->get('note'))->toBe('kept');
});

test('record uses the given channel and description overrides', function () {
    $this->service->record(
        AuditEvent::SETTING_UPDATED,
        description: 'Setting updated: mail.from',
        properties: ['key' => 'mail.from'],
        channel: AuditLogName::SETTINGS,
    );

    $row = AuditActivity::query()->latest('id')->firstOrFail();

    expect($row->log_name)->toBe('settings')
        ->and($row->description)->toBe('Setting updated: mail.from');
});

test('paginate delegates to the repository cursor pagination', function () {
    $filters = new AuditLogFilterDTO(perPage: 10);
    $paginator = Mockery::mock(CursorPaginator::class);

    $this->repository->shouldReceive('cursorPaginate')
        ->once()
        ->with($filters, 10, 'cursor-token')
        ->andReturn($paginator);

    expect($this->service->paginate($filters, 'cursor-token'))->toBe($paginator);
});

test('prune deletes entries older than each channel retention window', function () {
    $repository = app(AuditLogRepositoryInterface::class);

    $old = AuditActivity::query()->create([
        'log_name' => 'domain',
        'description' => 'old entry',
        'created_at' => now()->subDays(200),
        'updated_at' => now(),
    ]);

    $fresh = AuditActivity::query()->create([
        'log_name' => 'domain',
        'description' => 'fresh entry',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = new AuditLogService($repository);
    $service->prune();

    expect(AuditActivity::query()->whereKey($old->getKey())->exists())->toBeFalse()
        ->and(AuditActivity::query()->whereKey($fresh->getKey())->exists())->toBeTrue();
});
