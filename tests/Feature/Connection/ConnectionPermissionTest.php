<?php

use App\Enums\ApiRegion;
use App\Enums\AuditEvent;
use App\Enums\ConnectionStatus;
use App\Models\AuditActivity;
use App\Models\ClockifyConnection;
use App\Repositories\Contracts\ClockifyConnectionRepositoryInterface;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Http;

test('a viewer cannot perform connection lifecycle actions', function () {
    $viewer = makeUserWithPermissions(['connection.view']);
    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($viewer)->get(route('connections.index'))->assertOk();

    $this->actingAs($viewer)
        ->post(route('connections.store'), [
            'name' => 'New',
            'api_key' => 'key',
            'region' => ApiRegion::GLOBAL->value,
            'clockify_id' => 'w1',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->put(route('connections.update', $connection), ['name' => 'Renamed'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('connections.reverify', $connection))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->postJson(route('connections.rotate-key', $connection), ['api_key' => 'new-key'])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('connections.disable', $connection))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(route('connections.enable', $connection))
        ->assertForbidden();
});

test('a manager without the credential permission cannot rotate the key', function () {
    $manager = makeUserWithPermissions(['connection.view', 'connection.update']);
    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($manager)
        ->postJson(route('connections.rotate-key', $connection), ['api_key' => 'new-key'])
        ->assertForbidden();
});

test('a credential manager can rotate the key', function () {
    $rotator = makeUserWithPermissions(['connection.view', 'connection.credentials.update']);
    $connection = ClockifyConnection::factory()->create();

    Http::fake([
        '*/user' => Http::response(['id' => 'user-1', 'activeWorkspace' => 'w1']),
        '*/workspaces' => Http::response([
            ['id' => 'w1', 'name' => 'Acme', 'featureSubscriptionType' => 'STANDARD_2021'],
        ]),
    ]);

    $this->actingAs($rotator)
        ->postJson(route('connections.rotate-key', $connection), ['api_key' => 'new-key'])
        ->assertOk()
        ->assertJsonPath('ok', true);
});

test('audit rows are redacted so keys and tokens never persist', function () {
    $connection = ClockifyConnection::factory()->create();

    app(AuditLogService::class)->record(
        AuditEvent::CONNECTION_UPDATED,
        $connection,
        ['api_key' => 'super-secret-key', 'addon_token' => 'super-secret-token'],
    );

    $activity = AuditActivity::query()->latest('id')->first();

    $properties = $activity->properties->toJson();

    expect($activity->properties['api_key'])->toBe('[REDACTED]')
        ->and($activity->properties['addon_token'])->toBe('[REDACTED]')
        ->and($properties)->not->toContain('super-secret-key')
        ->and($properties)->not->toContain('super-secret-token');
});

test('the scheduler selects only active connections', function () {
    ClockifyConnection::factory()->create();
    ClockifyConnection::factory()->disabled()->create();

    $active = app(ClockifyConnectionRepositoryInterface::class)->allActive();

    expect($active)->toHaveCount(1)
        ->and($active->first()->status)->toBe(ConnectionStatus::ACTIVE);
});
