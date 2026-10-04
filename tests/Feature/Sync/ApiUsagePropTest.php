<?php

use App\Models\ClockifyConnection;
use Inertia\Testing\AssertableInertia as Assert;

test('the shared api usage prop reflects a free (hourly) connection', function () {
    ClockifyConnection::factory()->free()->create(['workspace_id' => 'ws-1']);

    $this->actingAs(makeUserWithPermissions(['connection.view']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('apiUsage.plan', 'free')
            ->where('apiUsage.window_type', 'hour')
            ->where('apiUsage.limit', 30)
            ->where('apiUsage.used', 0)
            ->where('apiUsage.remaining', 30)
            ->has('apiUsage.resets_at')
            ->has('apiUsage.resets_in')
            ->where('apiUsage.exhausted', false));
});

test('the shared api usage prop reflects a paid (per-second) connection', function () {
    ClockifyConnection::factory()->create([
        'requests_per_hour' => null,
        'requests_per_second' => 50,
    ]);

    $this->actingAs(makeUserWithPermissions(['connection.view']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('apiUsage.plan', 'paid')
            ->where('apiUsage.window_type', 'second')
            ->where('apiUsage.limit', 50));
});

test('the shared api usage prop is null without a connection', function () {
    $this->actingAs(makeUserWithPermissions(['connection.view']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('apiUsage', null));
});

test('the shared api usage prop is hidden without connection permission', function () {
    ClockifyConnection::factory()->create();

    $this->actingAs(makeUserWithPermissions(['report.view']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('apiUsage', null));
});
