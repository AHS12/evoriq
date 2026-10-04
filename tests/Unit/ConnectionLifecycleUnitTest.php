<?php

use App\DTOs\Connection\ConnectionVerificationResult;
use App\Enums\ApiErrorCode;
use App\Enums\ApiRegion;
use App\Enums\AuditEvent;
use App\Enums\ConnectionStatus;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Connection\ClockifyConnectionService;
use App\Services\Connection\ConnectionVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);

    $this->repository = Mockery::mock(ClockifyConnectionRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();
    $this->verifier = Mockery::mock(ConnectionVerifier::class);

    $this->service = new ClockifyConnectionService($this->repository, $this->audit, $this->verifier);
});

afterEach(function () {
    Mockery::close();
});

test('rename updates the name, re-resolves the endpoints and audits', function () {
    $connection = ClockifyConnection::factory()->make();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (ClockifyConnection $model, array $data): bool => $data['name'] === 'Renamed'
            && $data['region'] === ApiRegion::EU->value
            && $data['base_url'] === 'https://euc1.clockify.me/api/v1'
            && $data['reports_base_url'] === 'https://euc1.clockify.me/report/v1')
        ->andReturn($connection);

    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(fn (AuditEvent $event): bool => $event === AuditEvent::CONNECTION_UPDATED);

    expect($this->service->rename($connection, 'Renamed', ApiRegion::EU))->toBe($connection);
});

test('reverify delegates to the verifier', function () {
    $connection = ClockifyConnection::factory()->make();
    $result = ConnectionVerificationResult::failure(ApiErrorCode::CLOCKIFY_UNKNOWN, 'nope');

    $this->verifier->shouldReceive('verify')->once()->with($connection)->andReturn($result);

    expect($this->service->reverify($connection))->toBe($result);
});

test('rotateKey delegates to the verifier', function () {
    $connection = ClockifyConnection::factory()->make();
    $result = ConnectionVerificationResult::failure(ApiErrorCode::CLOCKIFY_UNKNOWN, 'nope');

    $this->verifier->shouldReceive('rotate')
        ->once()
        ->with($connection, 'new-key', null, ApiRegion::GLOBAL, null)
        ->andReturn($result);

    expect($this->service->rotateKey($connection, 'new-key', null, ApiRegion::GLOBAL))->toBe($result);
});

test('disable marks the connection disabled and audits', function () {
    $connection = ClockifyConnection::factory()->make();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (ClockifyConnection $model, array $data): bool => $data['status'] === ConnectionStatus::DISABLED->value)
        ->andReturn($connection);

    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(fn (AuditEvent $event): bool => $event === AuditEvent::CONNECTION_DISABLED);

    $this->service->disable($connection);
});

test('enable marks the connection active and audits', function () {
    $connection = ClockifyConnection::factory()->disabled()->make();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (ClockifyConnection $model, array $data): bool => $data['status'] === ConnectionStatus::ACTIVE->value)
        ->andReturn($connection);

    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(fn (AuditEvent $event): bool => $event === AuditEvent::CONNECTION_ENABLED);

    $this->service->enable($connection);
});

test('disabled connections are skipped by the active lookup', function () {
    ClockifyConnection::factory()->disabled()->create();

    expect(app(ClockifyConnectionRepositoryInterface::class)->findActive())->toBeNull();
});

test('rotating a key keeps the old key when the new one fails verification', function () {
    Http::fake(['*/user' => Http::response(['message' => 'Invalid API key'], 401)]);

    $connection = ClockifyConnection::factory()->create([
        'api_key' => 'old-key',
        'status' => ConnectionStatus::ACTIVE,
    ]);

    $result = app(ConnectionVerifier::class)->rotate($connection, 'new-key', null, ApiRegion::GLOBAL, null);

    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe(ApiErrorCode::CLOCKIFY_AUTHENTICATION_FAILED);

    $connection->refresh();

    expect($connection->api_key)->toBe('old-key')
        ->and($connection->status)->toBe(ConnectionStatus::ACTIVE);
});

test('rotating a key replaces the credential once the new key verifies', function () {
    Http::fake([
        '*/user' => Http::response(['id' => 'user-1', 'activeWorkspace' => 'w1']),
        '*/workspaces' => Http::response([
            ['id' => 'w1', 'name' => 'Acme', 'featureSubscriptionType' => 'STANDARD_2021'],
        ]),
    ]);

    $connection = ClockifyConnection::factory()->create(['api_key' => 'old-key']);

    $result = app(ConnectionVerifier::class)->rotate($connection, 'new-key', null, ApiRegion::GLOBAL, null);

    expect($result->ok)->toBeTrue();

    $connection->refresh();

    expect($connection->api_key)->toBe('new-key')
        ->and($connection->status)->toBe(ConnectionStatus::ACTIVE);
});
