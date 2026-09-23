<?php

use App\Helpers\EloquentFilterHelper;
use App\Models\User;

it('applies a case-insensitive like search across fields', function () {
    User::factory()->create(['name' => 'Alice Wonder']);
    User::factory()->create(['name' => 'Bob Builder']);

    $results = EloquentFilterHelper::applySearchFilters('alice', User::query(), ['name'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Alice Wonder');
});

it('applies exact-match select filters', function () {
    User::factory()->create(['email' => 'first@example.com']);
    User::factory()->create(['email' => 'second@example.com']);

    $results = EloquentFilterHelper::applySelectFilters(User::query(), ['email' => 'second@example.com'])->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->email)->toBe('second@example.com');
});

it('ignores empty select filter values', function () {
    User::factory()->count(2)->create();

    $results = EloquentFilterHelper::applySelectFilters(User::query(), ['email' => null, 'name' => ''])->get();

    expect($results)->toHaveCount(User::count());
});

it('applies an inclusive date range filter', function () {
    User::factory()->create(['created_at' => now()->subDays(10)]);
    User::factory()->create(['created_at' => now()->subDays(4)]);

    $results = EloquentFilterHelper::applyDateRangeFilter(
        User::query(),
        'created_at',
        now()->subDays(5)->toDateString(),
        now()->subDays(3)->toDateString(),
    )->get();

    expect($results)->toHaveCount(1);
});
