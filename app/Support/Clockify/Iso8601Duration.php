<?php

namespace App\Support\Clockify;

/**
 * Parses the ISO-8601 durations Clockify returns for a user's work capacity
 * (ENT-02) — e.g. `PT7H`, `PT7H30M`, `P1D`, `P1W` — into whole seconds so the
 * analytics engine can compare capacity with tracked time. Years/months are
 * intentionally ignored: they are not meaningful for a working week.
 */
final class Iso8601Duration
{
    public static function toSeconds(mixed $duration): ?int
    {
        if (! is_string($duration) || $duration === '') {
            return null;
        }

        $matched = preg_match(
            '/^P(?:(?<weeks>\d+)W)?(?:(?<days>\d+)D)?(?:T(?:(?<hours>\d+)H)?(?:(?<minutes>\d+)M)?(?:(?<seconds>\d+(?:\.\d+)?)S)?)?$/i',
            $duration,
            $parts,
        );

        if ($matched !== 1) {
            return null;
        }

        $seconds = ((int) ($parts['weeks'] ?? 0)) * 604800
            + ((int) ($parts['days'] ?? 0)) * 86400
            + ((int) ($parts['hours'] ?? 0)) * 3600
            + ((int) ($parts['minutes'] ?? 0)) * 60
            + (int) round((float) ($parts['seconds'] ?? 0));

        return $seconds > 0 ? $seconds : null;
    }
}
