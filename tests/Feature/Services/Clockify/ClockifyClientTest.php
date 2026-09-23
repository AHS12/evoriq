<?php

use App\Services\Clockify\ClockifyClient;
use App\Services\Clockify\ClockifyRateLimiter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

it('sends the api key to the configured base url', function () {
    Http::fake([
        'https://api.clockify.me/api/v1/workspaces/ws-1/projects*' => Http::response([['id' => 'p1']]),
    ]);

    $client = app(ClockifyClient::class)->forCredentials('secret-key');

    $response = $client->get('/workspaces/ws-1/projects');

    expect($response->successful())->toBeTrue();

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'secret-key')
        && str_contains($request->url(), '/workspaces/ws-1/projects'));
});

it('authenticates using an add-on token when provided', function () {
    Http::fake([
        'https://api.clockify.me/api/v1/workspaces*' => Http::response([]),
    ]);

    app(ClockifyClient::class)->forCredentials(null, 'addon-token')->get('/workspaces');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Addon-Token', 'addon-token'));
});

it('paginates until the last page header is reached', function () {
    Http::fake([
        'https://api.clockify.me/api/v1/workspaces/ws-1/tags*' => Http::sequence()
            ->push([['id' => 'a'], ['id' => 'b']], 200, ['Last-Page' => 'false'])
            ->push([['id' => 'c']], 200, ['Last-Page' => 'true']),
    ]);

    $client = app(ClockifyClient::class)->forCredentials('secret-key');

    $ids = collect($client->paginate('/workspaces/ws-1/tags', ['page-size' => 2], 'ws-1'))
        ->flatten(1)
        ->pluck('id')
        ->all();

    expect($ids)->toBe(['a', 'b', 'c']);
});

it('records a hit through the rate limiter', function () {
    $limiter = new ClockifyRateLimiter('test', requestsPerSecond: 5);

    $result = $limiter->attempt('ws-1', fn () => 'ok');

    expect($result)->toBe('ok')
        ->and(RateLimiter::attempts($limiter->limiterKey('ws-1')))->toBe(1);
});
