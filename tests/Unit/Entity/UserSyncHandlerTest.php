<?php

use App\Enums\ClockifyMembershipType;
use App\Enums\ClockifyUserStatus;
use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyMembership;
use App\Models\ClockifyUser;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Handlers\UserSyncHandler;
use App\Services\Sync\SyncContext;
use App\Services\Sync\SyncHandlerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.sleep' => 0]);
});

/**
 * Fetch → map → idempotent upsert exactly as the runner would (ENT-00).
 */
function fetchAndUpsertUsers(ClockifyWorkspace $workspace): void
{
    $handler = app(UserSyncHandler::class);
    $context = new SyncContext($workspace->connection, $workspace);

    $rows = [];

    foreach ($handler->fetchPage($context, 1) as $raw) {
        $rows[] = $handler->map($raw, $context);
    }

    $handler->repository()->upsertMany($workspace, $rows);
}

function makeUsersWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()->for($connection, 'connection')->create();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function userPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'user-1',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'status' => 'ACTIVE',
        'profilePicture' => 'https://cdn.example/ada.png',
        'settings' => [
            'timeZone' => 'Europe/London',
            'weekStart' => 'MONDAY',
            'workCapacity' => 'PT7H30M',
            'workingDays' => '["MONDAY","TUESDAY","WEDNESDAY","THURSDAY","FRIDAY"]',
        ],
        'memberships' => [
            [
                'membershipType' => 'WORKSPACE',
                'membershipStatus' => 'ACTIVE',
                'hourlyRate' => ['amount' => 60, 'currency' => 'GBP'],
                'costRate' => ['amount' => 30, 'currency' => 'GBP'],
            ],
            [
                'membershipType' => 'PROJECT',
                'targetId' => 'project-1',
                'hourlyRate' => ['amount' => 90, 'currency' => 'GBP'],
            ],
        ],
    ], $overrides);
}

test('the user handler is registered for the user entity', function () {
    expect(app(SyncHandlerRegistry::class)->has(SyncEntityType::USER))->toBeTrue();
});

test('it persists identity and capacity fields', function () {
    $workspace = makeUsersWorkspace();

    Http::fake(['*' => Http::response([userPayload()], 200)]);

    fetchAndUpsertUsers($workspace);

    $user = ClockifyUser::query()->firstOrFail();

    expect($user->clockify_id)->toBe('user-1')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email)->toBe('ada@example.com')
        ->and($user->status)->toBe(ClockifyUserStatus::ACTIVE)
        ->and($user->profile_picture_url)->toBe('https://cdn.example/ada.png')
        ->and($user->timezone)->toBe('Europe/London')
        ->and($user->week_start)->toBe('MONDAY')
        ->and($user->work_capacity)->toBe(27000)
        ->and($user->working_days)->toBe(['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'])
        ->and($user->raw_data)->toBeArray();
});

test('it derives only the workspace membership and its rates', function () {
    $workspace = makeUsersWorkspace();

    Http::fake(['*' => Http::response([userPayload()], 200)]);

    fetchAndUpsertUsers($workspace);

    $memberships = ClockifyMembership::query()->get();

    expect($memberships)->toHaveCount(1)
        ->and($memberships->first()->membership_type)->toBe(ClockifyMembershipType::WORKSPACE->value)
        ->and($memberships->first()->membership_status)->toBe('ACTIVE')
        ->and((float) $memberships->first()->hourly_rate_amount)->toBe(60.0)
        ->and($memberships->first()->hourly_rate_currency)->toBe('GBP')
        ->and((float) $memberships->first()->cost_rate_amount)->toBe(30.0)
        ->and($memberships->first()->cost_rate_currency)->toBe('GBP')
        ->and($memberships->first()->effective_from)->toBeNull();
});

test('a partial payload does not break the sync', function () {
    $workspace = makeUsersWorkspace();

    Http::fake(['*' => Http::response([[
        'id' => 'user-9',
        'name' => 'Grace Hopper',
    ]], 200)]);

    fetchAndUpsertUsers($workspace);

    $user = ClockifyUser::query()->firstOrFail();

    expect($user->name)->toBe('Grace Hopper')
        ->and($user->status)->toBeNull()
        ->and($user->timezone)->toBeNull()
        ->and($user->work_capacity)->toBeNull()
        ->and(ClockifyMembership::query()->count())->toBe(0);
});

test('re-syncing the same user does not duplicate it or its membership', function () {
    $workspace = makeUsersWorkspace();

    Http::fake(['*' => Http::response([userPayload()], 200)]);

    fetchAndUpsertUsers($workspace);
    fetchAndUpsertUsers($workspace);

    expect(ClockifyUser::query()->count())->toBe(1)
        ->and(ClockifyMembership::query()->count())->toBe(1);
});

test('a rate that changes at a new date is appended as history', function () {
    $workspace = makeUsersWorkspace();

    Http::fakeSequence('*')
        ->push([userPayload()], 200)
        ->push([userPayload([
            'memberships' => [[
                'membershipType' => 'WORKSPACE',
                'membershipStatus' => 'ACTIVE',
                'hourlyRate' => ['amount' => 80, 'currency' => 'GBP', 'since' => '2026-01-01T00:00:00Z'],
            ]],
        ])], 200);

    fetchAndUpsertUsers($workspace);
    fetchAndUpsertUsers($workspace);

    $memberships = ClockifyMembership::query()->orderBy('id')->get();

    expect($memberships)->toHaveCount(2)
        ->and($memberships[0]->effective_from)->toBeNull()
        ->and((float) $memberships[0]->hourly_rate_amount)->toBe(60.0)
        ->and($memberships[1]->effective_from->toDateString())->toBe('2026-01-01')
        ->and((float) $memberships[1]->hourly_rate_amount)->toBe(80.0);
});

test('re-syncing restores a soft-deleted user', function () {
    $workspace = makeUsersWorkspace();

    Http::fake(['*' => Http::response([userPayload()], 200)]);

    fetchAndUpsertUsers($workspace);

    $user = ClockifyUser::query()->firstOrFail();
    $user->delete();

    expect(ClockifyUser::query()->count())->toBe(0)
        ->and(ClockifyUser::withTrashed()->count())->toBe(1);

    fetchAndUpsertUsers($workspace);

    expect(ClockifyUser::query()->count())->toBe(1)
        ->and(ClockifyUser::query()->firstOrFail()->deleted_at)->toBeNull();
});
