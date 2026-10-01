<?php

use App\DTOs\Connection\ConnectionDTO;
use App\Enums\ApiRegion;
use App\Enums\AuditEvent;
use App\Enums\ConnectionStatus;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Connection\ClockifyConnectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->repository = Mockery::mock(ClockifyConnectionRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();

    $this->service = new ClockifyConnectionService($this->repository, $this->audit);
});

afterEach(function () {
    Mockery::close();
});

test('create resolves endpoint urls from the region and audits', function () {
    $connection = ClockifyConnection::factory()->make([
        'name' => 'Acme',
        'region' => ApiRegion::EU,
    ]);

    $this->repository->shouldReceive('create')
        ->once()
        ->withArgs(fn (array $data): bool => $data['name'] === 'Acme'
            && $data['base_url'] === 'https://euc1.clockify.me/api/v1'
            && $data['reports_base_url'] === 'https://acme.clockify.me/report/v1'
            && $data['status'] === ConnectionStatus::ACTIVE->value)
        ->andReturn($connection);

    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(fn (AuditEvent $event): bool => $event === AuditEvent::CONNECTION_CREATED);

    $dto = new ConnectionDTO(
        name: 'Acme',
        apiKey: 'secret',
        region: ApiRegion::EU,
        subdomain: 'acme',
    );

    expect($this->service->create($dto))->toBe($connection);
});

test('update re-resolves the endpoint urls and audits', function () {
    $connection = ClockifyConnection::factory()->make();

    $this->repository->shouldReceive('update')
        ->once()
        ->withArgs(fn (ClockifyConnection $model, array $data): bool => $data['region'] === ApiRegion::US->value
            && $data['base_url'] === 'https://use2.clockify.me/api/v1'
            && $data['reports_base_url'] === 'https://use2.clockify.me/report/v1')
        ->andReturn($connection);

    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(fn (AuditEvent $event): bool => $event === AuditEvent::CONNECTION_UPDATED);

    $dto = new ConnectionDTO(name: 'Acme', apiKey: 'secret', region: ApiRegion::US);

    expect($this->service->update($connection, $dto))->toBe($connection);
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

test('regions resolve their regular and reports base urls', function () {
    expect(ApiRegion::GLOBAL->baseUrl())->toBe('https://api.clockify.me/api/v1')
        ->and(ApiRegion::GLOBAL->reportsBaseUrl())->toBe('https://reports.api.clockify.me/v1')
        ->and(ApiRegion::US->baseUrl())->toBe('https://use2.clockify.me/api/v1')
        ->and(ApiRegion::US->reportsBaseUrl())->toBe('https://use2.clockify.me/report/v1')
        ->and(ApiRegion::DEVELOPER->reportsBaseUrl())->toBe('https://developer.clockify.me/report/v1')
        ->and(ApiRegion::EU->reportsBaseUrlFor('acme'))->toBe('https://acme.clockify.me/report/v1')
        ->and(ApiRegion::EU->reportsBaseUrlFor(null))->toBe('https://euc1.clockify.me/report/v1');
});

test('rate profile returns hourly limits for free and per-second for paid', function () {
    $free = ClockifyConnection::factory()->free()->make();
    $paid = ClockifyConnection::factory()->make([
        'requests_per_hour' => null,
        'requests_per_second' => 50,
    ]);
    $undetected = ClockifyConnection::factory()->make([
        'requests_per_hour' => null,
        'requests_per_second' => null,
    ]);

    expect($free->isFreePlan())->toBeTrue()
        ->and($free->rateProfile())->toMatchArray(['mode' => 'hourly', 'limit' => 30])
        ->and($paid->rateProfile())->toMatchArray(['mode' => 'per_second', 'limit' => 50])
        ->and($undetected->rateProfile())->toMatchArray(['mode' => 'per_second', 'limit' => 50]);
});

test('credentials are encrypted at rest and round-trip', function () {
    $connection = ClockifyConnection::factory()->create([
        'api_key' => 'plain-secret-key',
        'addon_token' => 'plain-addon-token',
    ]);

    $rawKey = DB::table('clockify_connections')->where('id', $connection->id)->value('api_key');
    $rawToken = DB::table('clockify_connections')->where('id', $connection->id)->value('addon_token');

    expect($rawKey)->not->toBe('plain-secret-key')
        ->and($rawToken)->not->toBe('plain-addon-token')
        ->and($connection->fresh()->api_key)->toBe('plain-secret-key')
        ->and($connection->fresh()->addon_token)->toBe('plain-addon-token')
        ->and($connection->toArray())->not->toHaveKey('api_key')
        ->and($connection->toArray())->not->toHaveKey('addon_token');
});
