<?php

use App\Events\Sync\ApiBudgetExhausted;
use App\Models\ClockifyApiUsage;
use App\Models\ClockifyConnection;
use App\Services\Clockify\ClockifyClient;
use App\Services\Sync\ApiUsageService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('the clockify client reserves and records against the connection budget', function () {
    $connection = ClockifyConnection::factory()->free()->create();

    Http::fake(['*' => Http::response(['ok' => true])]);

    app(ClockifyClient::class)->forConnection($connection)->get('/user');

    $snapshot = app(ApiUsageService::class)->snapshot($connection);

    expect($snapshot->used)->toBe(1)
        ->and($snapshot->lastRequestAt)->not->toBeNull();
});

test('the client waits and emits a budget-wait event when the window is spent', function () {
    $connection = ClockifyConnection::factory()->create([
        'requests_per_hour' => null,
        'requests_per_second' => 1,
    ]);

    Http::fake(['*' => Http::response(['ok' => true])]);

    $service = app(ApiUsageService::class);

    // Spend the only affordable slot in the current per-second window.
    expect($service->reserve($connection))->toBeTrue();

    Event::fake([ApiBudgetExhausted::class]);

    app(ClockifyClient::class)->forConnection($connection)->get('/user');

    Event::assertDispatched(ApiBudgetExhausted::class);

    // The request landed in the next window rather than overrunning this one.
    expect($service->snapshot($connection)->used)->toBe(1);
});

test('an unbound client does not touch the usage table', function () {
    Http::fake(['*' => Http::response(['ok' => true])]);

    app(ClockifyClient::class)->forCredentials('secret-key')->get('/user');

    expect(ClockifyApiUsage::query()->count())->toBe(0);
});

test('the usage endpoint exposes the current window for the active connection', function () {
    $user = makeUserWithPermissions(['connection.view']);

    $connection = ClockifyConnection::factory()->free()->create();
    app(ApiUsageService::class)->reserve($connection);

    $this->actingAs($user)
        ->getJson(route('api-usage.show'))
        ->assertOk()
        ->assertJsonPath('api_usage.plan', 'free')
        ->assertJsonPath('api_usage.window_type', 'hour')
        ->assertJsonPath('api_usage.used', 1)
        ->assertJsonPath('api_usage.limit', 30);
});

test('the usage endpoint returns null when there is no connection', function () {
    $user = makeUserWithPermissions(['connection.view']);

    $this->actingAs($user)
        ->getJson(route('api-usage.show'))
        ->assertOk()
        ->assertJsonPath('api_usage', null);
});
