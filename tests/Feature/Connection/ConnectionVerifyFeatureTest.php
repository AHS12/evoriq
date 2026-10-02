<?php

use App\Models\ClockifyConnection;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);
});

test('the verify endpoint returns the typed result and never echoes the key', function () {
    $user = makeUserWithPermissions(['connection.view', 'connection.update']);

    Http::fake([
        '*/user' => Http::response(['id' => 'user-1', 'activeWorkspace' => 'w1']),
        '*/workspaces' => Http::response([
            [
                'id' => 'w1',
                'name' => 'Acme',
                'featureSubscriptionType' => 'STANDARD_2021',
                'features' => ['REPORTS'],
            ],
        ]),
    ]);

    $connection = ClockifyConnection::factory()->create(['api_key' => 'top-secret-key']);

    $response = $this->actingAs($user)->postJson(route('connections.reverify', $connection));

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('profile.plan', 'paid')
        ->assertJsonCount(1, 'workspaces')
        ->assertJsonPath('workspaces.0.name', 'Acme');

    expect($response->getContent())->not->toContain('top-secret-key');
});

test('verifying a connection requires the update permission', function () {
    $user = makeUserWithPermissions(['connection.view']);

    $connection = ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->postJson(route('connections.reverify', $connection))
        ->assertForbidden();
});
