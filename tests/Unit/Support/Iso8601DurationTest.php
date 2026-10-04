<?php

use App\Support\Clockify\Iso8601Duration;

test('it parses common ISO-8601 durations into seconds', function () {
    expect(Iso8601Duration::toSeconds('PT7H'))->toBe(25200)
        ->and(Iso8601Duration::toSeconds('PT7H30M'))->toBe(27000)
        ->and(Iso8601Duration::toSeconds('P1D'))->toBe(86400)
        ->and(Iso8601Duration::toSeconds('P1W'))->toBe(604800)
        ->and(Iso8601Duration::toSeconds('PT45S'))->toBe(45);
});

test('it returns null for missing or unparseable durations', function () {
    expect(Iso8601Duration::toSeconds(null))->toBeNull()
        ->and(Iso8601Duration::toSeconds(''))->toBeNull()
        ->and(Iso8601Duration::toSeconds('not-a-duration'))->toBeNull()
        ->and(Iso8601Duration::toSeconds('PT0S'))->toBeNull()
        ->and(Iso8601Duration::toSeconds(3600))->toBeNull();
});
