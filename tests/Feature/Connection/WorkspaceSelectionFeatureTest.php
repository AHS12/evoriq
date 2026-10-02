<?php

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;

test('selecting a workspace marks it active and updates the connection', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);

    $connection = ClockifyConnection::factory()->create();
    $first = ClockifyWorkspace::factory()->create([
        'connection_id' => $connection->id,
        'clockify_id' => 'w1',
        'active' => true,
    ]);
    $second = ClockifyWorkspace::factory()->create([
        'connection_id' => $connection->id,
        'clockify_id' => 'w2',
        'active' => false,
    ]);

    $this->actingAs($user)
        ->putJson(route('connections.workspace.update', $connection), ['clockify_id' => 'w2'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('workspace.clockify_id', 'w2');

    expect($first->fresh()->active)->toBeFalsy()
        ->and($second->fresh()->active)->toBeTruthy()
        ->and($connection->fresh()->workspace_id)->toBe('w2');
});

test('selecting a workspace requires the update permission', function () {
    $user = makeUserWithPermissions(['connection.view']);

    $connection = ClockifyConnection::factory()->create();
    ClockifyWorkspace::factory()->create([
        'connection_id' => $connection->id,
        'clockify_id' => 'w1',
    ]);

    $this->actingAs($user)
        ->putJson(route('connections.workspace.update', $connection), ['clockify_id' => 'w1'])
        ->assertForbidden();
});

test('selecting an unknown workspace is rejected', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);

    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->putJson(route('connections.workspace.update', $connection), ['clockify_id' => 'missing'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['clockify_id']);
});
