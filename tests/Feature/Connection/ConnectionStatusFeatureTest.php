<?php

use App\Models\ClockifyConnection;
use Inertia\Testing\AssertableInertia as Assert;

test('the connections page lists a credential-safe connection card', function () {
    $user = makeUserWithPermissions(['connection.view']);
    ClockifyConnection::factory()->create([
        'name' => 'Acme Clockify',
        'api_key' => 'secret-key-should-not-leak',
        'workspace_id' => 'ws-1',
    ]);

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertOk()
        ->assertDontSee('secret-key-should-not-leak')
        ->assertInertia(fn (Assert $page) => $page
            ->component('connections/index')
            ->has('connections.data', 1)
            ->where('connections.data.0.name', 'Acme Clockify')
            ->where('connections.data.0.status', 'active')
            ->where('connections.data.0.workspace.id', 'ws-1')
            ->missing('connections.data.0.api_key')
            ->missing('connections.data.0.addon_token'));
});

test('the dashboard includes the connection status summary', function () {
    ClockifyConnection::factory()->free()->create(['workspace_id' => 'ws-1']);

    $this->actingAs(superAdmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('connectionStatus')
            ->where('connectionStatus.plan.is_free', true)
            ->missing('connectionStatus.api_key'));
});

test('the dashboard status summary is null without a connection', function () {
    $this->actingAs(superAdmin())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('connectionStatus', null));
});
