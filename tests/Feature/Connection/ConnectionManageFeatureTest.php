<?php

use App\Enums\AuditEvent;
use App\Enums\ConnectionStatus;
use App\Models\AuditActivity;
use App\Models\ClockifyConnection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);
});

test('a manager can rename a connection', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);
    $connection = ClockifyConnection::factory()->create(['name' => 'Old name']);

    $this->actingAs($user)
        ->put(route('connections.update', $connection), ['name' => 'New name'])
        ->assertRedirect(route('connections.index'));

    expect($connection->fresh()->name)->toBe('New name');

    expect(AuditActivity::query()->where('event', AuditEvent::CONNECTION_UPDATED->value)->exists())->toBeTrue();
});

test('renaming requires the update permission', function () {
    $user = makeUserWithPermissions(['connection.view']);
    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->put(route('connections.update', $connection), ['name' => 'New name'])
        ->assertForbidden();
});

test('a manager can disable and re-enable a connection', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);
    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->post(route('connections.disable', $connection))
        ->assertRedirect(route('connections.index'));

    expect($connection->fresh()->status)->toBe(ConnectionStatus::DISABLED);
    expect(AuditActivity::query()->where('event', AuditEvent::CONNECTION_DISABLED->value)->exists())->toBeTrue();

    $this->actingAs($user)
        ->post(route('connections.enable', $connection))
        ->assertRedirect(route('connections.index'));

    expect($connection->fresh()->status)->toBe(ConnectionStatus::ACTIVE);
    expect(AuditActivity::query()->where('event', AuditEvent::CONNECTION_ENABLED->value)->exists())->toBeTrue();
});

test('a rejected key rotation keeps the old key and never echoes the key', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.credentials.update']);
    $connection = ClockifyConnection::factory()->create(['api_key' => 'old-key']);

    Http::fake(['*/user' => Http::response(['message' => 'Invalid API key'], 401)]);

    $response = $this->actingAs($user)
        ->postJson(route('connections.rotate-key', $connection), ['api_key' => 'bad-key']);

    $response->assertOk()->assertJsonPath('ok', false);

    expect($response->getContent())->not->toContain('bad-key')
        ->and($connection->fresh()->api_key)->toBe('old-key')
        ->and($connection->fresh()->status)->toBe(ConnectionStatus::ACTIVE);
});

test('a verified key rotation replaces the credential and audits', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.credentials.update']);
    $connection = ClockifyConnection::factory()->create(['api_key' => 'old-key']);

    Http::fake([
        '*/user' => Http::response(['id' => 'user-1', 'activeWorkspace' => 'w1']),
        '*/workspaces' => Http::response([
            ['id' => 'w1', 'name' => 'Acme', 'featureSubscriptionType' => 'STANDARD_2021'],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('connections.rotate-key', $connection), ['api_key' => 'new-key'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($connection->fresh()->api_key)->toBe('new-key');
    expect(AuditActivity::query()->where('event', AuditEvent::CONNECTION_KEY_ROTATED->value)->exists())->toBeTrue();
});

test('rotating a key requires the credential permission', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);
    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->postJson(route('connections.rotate-key', $connection), ['api_key' => 'new-key'])
        ->assertForbidden();
});

test('lifecycle actions record the acting user on the audit row', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);
    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($user)->post(route('connections.disable', $connection));

    $activity = AuditActivity::query()
        ->where('event', AuditEvent::CONNECTION_DISABLED->value)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($user->id);
});
