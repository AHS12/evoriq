<?php

use App\Http\Resources\Connection\ConnectionStatusResource;
use App\Models\ClockifyConnection;
use App\Services\Connection\ConnectionStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the status resource projects plan, budget and workspace without secrets', function () {
    $connection = ClockifyConnection::factory()->free()->create([
        'workspace_id' => 'ws-9',
        'requests_per_hour' => 30,
        'requests_per_second' => null,
    ]);

    $array = ConnectionStatusResource::make($connection)->resolve(Request::create('/'));

    expect($array)
        ->toHaveKeys(['id', 'name', 'status', 'workspace', 'plan', 'budget', 'last_verified_at'])
        ->and($array['workspace']['id'])->toBe('ws-9')
        ->and($array['plan']['is_free'])->toBeTrue()
        ->and($array['plan']['rate']['mode'])->toBe('hourly')
        ->and($array['budget']['requests_per_hour'])->toBe(30)
        ->not->toHaveKey('api_key')
        ->not->toHaveKey('addon_token');
});

test('the status service resolves the active connection for the organization', function () {
    expect(app(ConnectionStatusService::class)->forOrganization())->toBeNull();

    $connection = ClockifyConnection::factory()->create();

    expect(app(ConnectionStatusService::class)->forOrganization()?->id)
        ->toBe($connection->id);
});
