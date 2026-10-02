<?php

use App\Enums\ApiErrorCode;
use App\Enums\ApiRegion;
use App\Enums\AuditEvent;
use App\Enums\ConnectionStatus;
use App\Models\AuditActivity;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Connection\ConnectionVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);
});

/**
 * Fake the two Clockify probe endpoints for a successful verification.
 *
 * @param  array<int, array<string, mixed>>  $workspaces
 */
function fakeClockify(array $workspaces): void
{
    Http::fake([
        '*/user' => Http::response([
            'id' => 'user-1',
            'email' => 'ada@example.com',
            'activeWorkspace' => $workspaces[0]['id'] ?? null,
        ]),
        '*/workspaces' => Http::response($workspaces),
    ]);
}

test('a valid key yields a verified profile and upserts workspaces', function () {
    fakeClockify([
        [
            'id' => 'w1',
            'name' => 'Acme',
            'subdomain' => ['name' => 'acme', 'enabled' => true],
            'currency' => 'USD',
            'timeZone' => 'UTC',
            'weekStart' => 'MONDAY',
            'featureSubscriptionType' => 'STANDARD_2021',
            'features' => ['REPORTS'],
        ],
        ['id' => 'w2', 'name' => 'Beta', 'featureSubscriptionType' => 'FREE', 'features' => []],
    ]);

    $connection = ClockifyConnection::factory()->create(['api_key' => 'secret-key']);

    $result = app(ConnectionVerifier::class)->verify($connection);

    expect($result->ok)->toBeTrue()
        ->and($result->profile?->plan)->toBe('paid')
        ->and($result->profile?->requestsPerSecond)->toBe(50)
        ->and($result->profile?->requestsPerHour)->toBeNull()
        ->and($result->workspaces)->toHaveCount(2);

    $connection->refresh();

    expect($connection->status)->toBe(ConnectionStatus::ACTIVE)
        ->and($connection->workspace_id)->toBe('w1')
        ->and($connection->subdomain)->toBe('acme')
        ->and($connection->reports_base_url)->toBe('https://acme.clockify.me/report/v1')
        ->and($connection->last_verified_at)->not->toBeNull();

    expect(ClockifyWorkspace::query()->count())->toBe(2)
        ->and(ClockifyWorkspace::query()->where('clockify_id', 'w1')->value('active'))->toBeTruthy()
        ->and(ClockifyWorkspace::query()->where('clockify_id', 'w2')->value('active'))->toBeFalsy()
        ->and(AuditActivity::query()->where('event', AuditEvent::CONNECTION_VERIFIED->value)->exists())->toBeTrue();
});

test('a bad key marks the connection invalid with a mapped error and no key leak', function () {
    Http::fake(['*/user' => Http::response(['message' => 'Invalid API key'], 401)]);

    $connection = ClockifyConnection::factory()->create(['api_key' => 'secret-key']);

    $result = app(ConnectionVerifier::class)->verify($connection);

    expect($result->ok)->toBeFalse()
        ->and($result->errorCode)->toBe(ApiErrorCode::CLOCKIFY_AUTHENTICATION_FAILED);

    $connection->refresh();

    expect($connection->status)->toBe(ConnectionStatus::INVALID)
        ->and($connection->last_error)->toBe('Invalid API key')
        ->and($connection->last_error)->not->toContain('secret-key');

    expect(ClockifyWorkspace::query()->count())->toBe(0)
        ->and(AuditActivity::query()->where('event', AuditEvent::CONNECTION_VALIDATION_FAILED->value)->exists())->toBeTrue();
});

test('rate limiting maps to the rate limited code with retry-after', function () {
    Http::fake([
        '*/user' => Http::response(['message' => 'Too many requests'], 429, ['Retry-After' => '120']),
    ]);

    $connection = ClockifyConnection::factory()->create();

    $result = app(ConnectionVerifier::class)->verify($connection);

    expect($result->errorCode)->toBe(ApiErrorCode::CLOCKIFY_RATE_LIMITED)
        ->and($result->retryAfter)->toBe(120);
});

test('a 5xx response maps to api unavailable', function () {
    Http::fake(['*/user' => Http::response('', 503)]);

    $connection = ClockifyConnection::factory()->create();

    expect(app(ConnectionVerifier::class)->verify($connection)->errorCode)
        ->toBe(ApiErrorCode::CLOCKIFY_API_UNAVAILABLE);
});

test('an unknown plan falls back to the free hourly budget', function () {
    fakeClockify([['id' => 'w1', 'name' => 'Solo']]);

    $connection = ClockifyConnection::factory()->create();

    $result = app(ConnectionVerifier::class)->verify($connection);

    expect($result->profile?->plan)->toBe('free')
        ->and($result->profile?->requestsPerHour)->toBe(30)
        ->and($result->profile?->requestsPerSecond)->toBeNull();
});

test('regional connections resolve the regional reports host', function () {
    fakeClockify([['id' => 'w1', 'name' => 'EU', 'featureSubscriptionType' => 'STANDARD_2021']]);

    $connection = ClockifyConnection::factory()->forRegion(ApiRegion::EU)->create();

    app(ConnectionVerifier::class)->verify($connection);

    expect($connection->fresh()->reports_base_url)->toBe('https://euc1.clockify.me/report/v1');
});

test('re-verifying updates workspaces without duplicating rows', function () {
    $name = 'Acme';

    Http::fake([
        '*/user' => Http::response(['id' => 'user-1', 'activeWorkspace' => 'w1']),
        '*/workspaces' => function () use (&$name) {
            return Http::response([
                ['id' => 'w1', 'name' => $name, 'featureSubscriptionType' => 'STANDARD_2021'],
            ]);
        },
    ]);

    $connection = ClockifyConnection::factory()->create();

    app(ConnectionVerifier::class)->verify($connection);

    $name = 'Acme Renamed';

    app(ConnectionVerifier::class)->verify($connection->fresh());

    expect(ClockifyWorkspace::query()->count())->toBe(1)
        ->and(ClockifyWorkspace::query()->value('name'))->toBe('Acme Renamed');
});
