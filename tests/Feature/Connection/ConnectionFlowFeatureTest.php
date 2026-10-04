<?php

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);
});

function fakeConnectClockify(): void
{
    Http::fake([
        '*/user' => Http::response([
            'id' => 'user-1',
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'activeWorkspace' => 'w1',
        ]),
        '*/workspaces' => Http::response([
            [
                'id' => 'w1',
                'name' => 'Acme',
                'subdomain' => 'acme',
                'currency' => 'USD',
                'timeZone' => 'UTC',
                'featureSubscriptionType' => 'STANDARD_2021',
                'features' => ['REPORTS'],
            ],
        ]),
    ]);
}

test('the connections index renders for permitted users', function () {
    $user = makeUserWithPermissions(['connection.view']);
    ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('connections/index')
            ->has('connections.data', 1)
            ->has('regions')
            ->where('canCreate', false));
});

test('the connections index is forbidden without permission', function () {
    $user = makeUserWithPermissions(['file.view']);

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertForbidden();
});

test('verify returns the typed result without persisting or echoing the key', function () {
    $user = makeUserWithPermissions(['connection.create']);
    fakeConnectClockify();

    $response = $this->actingAs($user)->postJson(route('connections.verify'), [
        'api_key' => 'super-secret-key',
        'region' => 'global',
    ]);

    $response->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('profile.plan', 'paid')
        ->assertJsonPath('account.email', 'ada@example.com')
        ->assertJsonCount(1, 'workspaces')
        ->assertJsonPath('workspaces.0.clockify_id', 'w1');

    expect($response->getContent())->not->toContain('super-secret-key')
        ->and(ClockifyConnection::query()->count())->toBe(0);
});

test('verify validates the input', function () {
    $user = makeUserWithPermissions(['connection.create']);

    $this->actingAs($user)
        ->postJson(route('connections.verify'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['api_key', 'region']);
});

test('store persists the connection with the chosen active workspace', function () {
    $user = makeUserWithPermissions(['connection.create']);
    fakeConnectClockify();

    $this->actingAs($user)
        ->post(route('connections.store'), [
            'name' => 'My Clockify',
            'api_key' => 'super-secret-key',
            'region' => 'global',
            'clockify_id' => 'w1',
        ])
        ->assertRedirect(route('connections.index'));

    $connection = ClockifyConnection::query()->firstOrFail();

    expect($connection->name)->toBe('My Clockify')
        ->and($connection->status->value)->toBe('active')
        ->and($connection->workspace_id)->toBe('w1')
        ->and($connection->api_key)->toBe('super-secret-key');

    $workspace = ClockifyWorkspace::query()
        ->where('connection_id', $connection->id)
        ->firstOrFail();

    expect($workspace->clockify_id)->toBe('w1')
        ->and($workspace->active)->toBeTrue();
});

test('store drops the connection when verification fails', function () {
    $user = makeUserWithPermissions(['connection.create']);

    Http::fake(['*/user' => Http::response(['message' => 'Invalid API key'], 401)]);

    $this->actingAs($user)
        ->post(route('connections.store'), [
            'api_key' => 'bad-key',
            'region' => 'global',
            'clockify_id' => 'w1',
        ])
        ->assertRedirect();

    expect(ClockifyConnection::query()->count())->toBe(0);
});

test('store requires the create permission', function () {
    $user = makeUserWithPermissions(['connection.view']);

    $this->actingAs($user)
        ->post(route('connections.store'), [
            'api_key' => 'x',
            'region' => 'global',
            'clockify_id' => 'w1',
        ])
        ->assertForbidden();
});

test('a second connection cannot be added (single-connection MVP)', function () {
    $user = makeUserWithPermissions(['connection.create']);
    ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->post(route('connections.store'), [
            'name' => 'Second',
            'api_key' => 'key',
            'region' => 'global',
            'clockify_id' => 'w1',
        ])
        ->assertRedirect();

    expect(ClockifyConnection::query()->count())->toBe(1);
});

test('the add action is hidden once a connection exists', function () {
    $user = makeUserWithPermissions(['connection.create', 'connection.view']);
    ClockifyConnection::factory()->create();

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', false));
});

test('the add action is available before any connection exists', function () {
    $user = makeUserWithPermissions(['connection.create', 'connection.view']);

    $this->actingAs($user)
        ->get(route('connections.index'))
        ->assertInertia(fn (Assert $page) => $page->where('canCreate', true));
});
