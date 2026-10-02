<?php

use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Connection\WorkspaceService;

test('syncFromConnection persists a realistic Clockify workspace payload', function () {
    $connection = ClockifyConnection::factory()->create();

    $payload = [[
        'id' => '68866b1c06b68d6e9a719271',
        'name' => 'Innovix Matrix System',
        'subdomain' => ['name' => 'innovix', 'enabled' => true],
        'hourlyRate' => ['amount' => 0, 'currency' => 'USD'],
        'features' => ['ONE_MONTH_RANGE_REPORTS', 'TIME_TRACKING'],
        'featureSubscriptionType' => 'FREE_2026',
        'workspaceSettings' => ['weekStart' => 'SATURDAY'],
        'currencies' => [['code' => 'USD', 'isDefault' => true]],
        'cakeOrganizationId' => '68335ed8f89dcf67d78cef2e',
    ]];

    app(WorkspaceService::class)->syncFromConnection($connection, $payload, '68866b1c06b68d6e9a719271');

    $workspace = ClockifyWorkspace::query()->firstOrFail();

    expect($workspace->clockify_id)->toBe('68866b1c06b68d6e9a719271')
        ->and($workspace->subdomain)->toBe('innovix')
        ->and($workspace->currency)->toBe('USD')
        ->and($workspace->week_start)->toBe('SATURDAY')
        ->and($workspace->features)->toBe(['ONE_MONTH_RANGE_REPORTS', 'TIME_TRACKING'])
        ->and($workspace->raw_data)->toBeArray()
        ->and($workspace->active)->toBeTrue();
});
