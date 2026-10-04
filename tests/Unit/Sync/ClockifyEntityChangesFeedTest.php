<?php

use App\Enums\EntityChangeType;
use App\Enums\SyncEntityType;
use App\Models\ClockifyConnection;
use App\Models\ClockifyWorkspace;
use App\Services\Sync\Contracts\ChangeFeed;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['clockify.retry.times' => 1, 'clockify.retry.sleep' => 0]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function changeFeedWorkspace(): ClockifyWorkspace
{
    $connection = ClockifyConnection::factory()->create();

    return ClockifyWorkspace::factory()
        ->for($connection, 'connection')
        ->create(['clockify_id' => 'ws-1']);
}

test('it fetches created, updated and deleted changes for a type', function () {
    Http::fake([
        '*/workspaces/ws-1/entities/created*' => Http::response([['id' => 'e1', 'updatedAt' => '2026-06-01T10:00:00Z']]),
        '*/workspaces/ws-1/entities/updated*' => Http::response([['id' => 'e2']]),
        '*/workspaces/ws-1/entities/deleted*' => Http::response(['response' => [['id' => 'e3', 'deletedAt' => '2026-06-02T10:00:00Z']]]),
    ]);

    $page = app(ChangeFeed::class)->since(
        changeFeedWorkspace(),
        CarbonImmutable::parse('2026-05-01'),
        CarbonImmutable::parse('2026-06-15'),
        SyncEntityType::TIME_ENTRY,
    );

    expect($page->items)->toHaveCount(3)
        ->and(array_map(fn ($item) => $item->changeType, $page->items))->toContain(
            EntityChangeType::CREATED,
            EntityChangeType::UPDATED,
            EntityChangeType::DELETED,
        )
        ->and($page->hasMore)->toBeFalse();
});

test('it handles the bare-array deleted shape', function () {
    Http::fake([
        '*/workspaces/ws-1/entities/created*' => Http::response([]),
        '*/workspaces/ws-1/entities/updated*' => Http::response([]),
        '*/workspaces/ws-1/entities/deleted*' => Http::response([['id' => 'e9']]),
    ]);

    $page = app(ChangeFeed::class)->since(
        changeFeedWorkspace(),
        CarbonImmutable::parse('2026-06-14'),
        CarbonImmutable::parse('2026-06-15'),
        SyncEntityType::PROJECTS,
    );

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->changeType)->toBe(EntityChangeType::DELETED)
        ->and($page->items[0]->clockifyId)->toBe('e9');
});

test('it paginates with page and limit and reports more pages', function () {
    config(['clockify.change_feed.limit' => 2]);

    Http::fake([
        '*/workspaces/ws-1/entities/created*' => Http::response([['id' => 'e1'], ['id' => 'e2']]),
        '*/workspaces/ws-1/entities/updated*' => Http::response([]),
        '*/workspaces/ws-1/entities/deleted*' => Http::response([]),
    ]);

    $page = app(ChangeFeed::class)->since(
        changeFeedWorkspace(),
        CarbonImmutable::parse('2026-06-14'),
        CarbonImmutable::parse('2026-06-15'),
        SyncEntityType::TIME_ENTRY,
        page: 0,
    );

    expect($page->hasMore)->toBeTrue()
        ->and($page->page)->toBe(0)
        ->and($page->items)->toHaveCount(2);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'page=0')
        && str_contains($request->url(), 'limit=2'));
});

test('a disabled feed returns no changes and sends no requests', function () {
    config(['clockify.change_feed.enabled' => false]);
    Http::fake();

    $page = app(ChangeFeed::class)->since(
        changeFeedWorkspace(),
        CarbonImmutable::parse('2026-06-14'),
        CarbonImmutable::parse('2026-06-15'),
        SyncEntityType::TIME_ENTRY,
    );

    expect($page->isEmpty())->toBeTrue();
    Http::assertNothingSent();
});
