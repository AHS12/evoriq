<?php

use App\Models\ClockifyApiUsage;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use Illuminate\Support\Facades\Http;

test('the import inspect endpoint plans for the active connection', function () {
    $user = makeUserWithPermissions(['sync.trigger']);

    $connection = ClockifyConnection::factory()->free()->create(['workspace_id' => 'ws-1']);
    ClockifyWorkspace::factory()->for($connection, 'connection')->create(['clockify_id' => 'ws-1']);

    Http::fake([
        '*/workspaces/ws-1/users*' => Http::response(
            [['id' => 'u1', 'name' => 'Ada']],
            200,
            ['Last-Page' => 'true'],
        ),
    ]);

    $this->actingAs($user)
        ->postJson(route('import.inspect'), [
            'range_start' => '2026-01-01',
            'range_end' => '2026-02-01',
        ])
        ->assertOk()
        ->assertJsonPath('mode', 'initial')
        ->assertJsonPath('total_jobs', 11)
        ->assertJsonPath('estimate.window_type', 'hour')
        ->assertJsonPath('phases.0.phase', 'reference')
        ->assertJsonPath('phases.1.phase', 'fact');
});

test('the import inspect endpoint requires the sync trigger permission', function () {
    $user = makeUserWithPermissions(['connection.view']);

    $this->actingAs($user)
        ->postJson(route('import.inspect'), [
            'range_start' => '2026-01-01',
            'range_end' => '2026-02-01',
        ])
        ->assertForbidden();
});

test('the import inspect endpoint validates the range', function () {
    $user = makeUserWithPermissions(['sync.trigger']);

    $this->actingAs($user)
        ->postJson(route('import.inspect'), [
            'range_start' => '2026-02-01',
            'range_end' => '2026-01-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['range_end']);
});

test('the import inspect endpoint fails fast when the api budget is exhausted', function () {
    $user = makeUserWithPermissions(['sync.trigger']);

    $connection = ClockifyConnection::factory()->free()->create(['workspace_id' => 'ws-1']);
    $workspace = ClockifyWorkspace::factory()->for($connection, 'connection')->create(['clockify_id' => 'ws-1']);

    ClockifyApiUsage::factory()->create([
        'connection_id' => $connection->id,
        'workspace_id' => $workspace->id,
        'requests_used' => 30,
        'requests_remaining' => 0,
        'limit_requests' => 30,
    ]);

    // A stray request would hit the network; the endpoint must refuse before it.
    Http::fake(['*' => Http::response([], 200, ['Last-Page' => 'true'])]);

    $this->actingAs($user)
        ->postJson(route('import.inspect'), [
            'range_start' => '2026-01-01',
            'range_end' => '2026-02-01',
        ])
        ->assertStatus(429)
        ->assertJsonPath('resets_in', fn (mixed $value): bool => is_int($value));
});
