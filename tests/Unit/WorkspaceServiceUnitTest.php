<?php

use App\Enums\AuditEvent;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Repositories\Contracts\ClockifyWorkspaceRepositoryInterface;
use App\Services\Audit\AuditLogService;
use App\Services\Connection\WorkspaceService;
use App\Support\Clockify\WorkspaceMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->workspaces = Mockery::mock(ClockifyWorkspaceRepositoryInterface::class);
    $this->connections = Mockery::mock(ClockifyConnectionRepositoryInterface::class);
    $this->audit = Mockery::mock(AuditLogService::class);
    $this->audit->shouldReceive('record')->byDefault();

    $this->service = new WorkspaceService($this->workspaces, $this->connections, $this->audit, new WorkspaceMapper);
});

afterEach(function () {
    Mockery::close();
});

test('syncFromConnection normalizes payloads and marks the active workspace', function () {
    $connection = ClockifyConnection::factory()->make(['id' => 5]);

    $this->workspaces->shouldReceive('upsertMany')
        ->once()
        ->withArgs(fn (ClockifyConnection $model, array $rows): bool => count($rows) === 2
            && $rows[0]['clockify_id'] === 'w1'
            && $rows[0]['time_zone'] === 'UTC'
            && $rows[0]['active'] === true
            && $rows[1]['active'] === false)
        ->andReturn(new Collection);

    $this->service->syncFromConnection($connection, [
        ['id' => 'w1', 'name' => 'Acme', 'timeZone' => 'UTC'],
        ['id' => 'w2', 'name' => 'Beta'],
    ], 'w1');
});

test('syncFromConnection defaults the first workspace to active', function () {
    $connection = ClockifyConnection::factory()->make(['id' => 5]);

    $this->workspaces->shouldReceive('upsertMany')
        ->once()
        ->withArgs(fn (ClockifyConnection $model, array $rows): bool => $rows[0]['active'] === true
            && $rows[1]['active'] === false)
        ->andReturn(new Collection);

    $this->service->syncFromConnection($connection, [
        ['id' => 'w1', 'name' => 'Acme'],
        ['id' => 'w2', 'name' => 'Beta'],
    ]);
});

test('selectActive marks one workspace active, updates the connection and audits', function () {
    $connection = ClockifyConnection::factory()->make(['id' => 5]);
    $workspace = ClockifyWorkspace::factory()->make([
        'id' => 9,
        'connection_id' => 5,
        'clockify_id' => 'w2',
        'active' => false,
    ]);

    $this->workspaces->shouldReceive('findByClockifyId')->once()->with(5, 'w2')->andReturn($workspace);
    $this->workspaces->shouldReceive('markActive')->once()->with($connection, 'w2');
    $this->connections->shouldReceive('update')->once()->with($connection, ['workspace_id' => 'w2'])->andReturn($connection);
    $this->audit->shouldReceive('record')
        ->once()
        ->withArgs(fn (AuditEvent $event): bool => $event === AuditEvent::WORKSPACE_SELECTED);

    expect($this->service->selectActive($connection, 'w2'))->toBe($workspace);
});

test('selectActive rejects an unknown workspace', function () {
    $connection = ClockifyConnection::factory()->make(['id' => 5]);

    $this->workspaces->shouldReceive('findByClockifyId')->once()->andReturn(null);
    $this->connections->shouldReceive('update')->never();

    expect(fn () => $this->service->selectActive($connection, 'missing'))
        ->toThrow(RuntimeException::class);
});
