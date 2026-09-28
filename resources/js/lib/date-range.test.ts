import { describe, expect, it } from 'vitest';
import {
    granularityForRange,
    matchPreset,
    parseISODate,
    previousPeriod,
    resolvePreset,
    samePeriodLastYear,
    toISODate,
} from '@/lib/date-range';

// Monday, September 28 2026.
const now = new Date(2026, 8, 28);

describe('resolvePreset', () => {
    it('resolves day presets', () => {
        expect(toISODate(resolvePreset('today', { now }).start)).toBe(
            '2026-09-28',
        );
        expect(toISODate(resolvePreset('yesterday', { now }).end)).toBe(
            '2026-09-27',
        );
    });

    it('resolves ISO weeks (Monday–Sunday)', () => {
        const range = resolvePreset('this-week', { now });

        expect(toISODate(range.start)).toBe('2026-09-28');
        expect(toISODate(range.end)).toBe('2026-10-04');
    });

    it('resolves calendar months and quarters', () => {
        const month = resolvePreset('this-month', { now });
        const quarter = resolvePreset('this-quarter', { now });

        expect(toISODate(month.start)).toBe('2026-09-01');
        expect(toISODate(month.end)).toBe('2026-09-30');
        expect(toISODate(quarter.start)).toBe('2026-07-01');
        expect(toISODate(quarter.end)).toBe('2026-09-30');
    });

    it('returns an open range for all-time without bounds', () => {
        expect(resolvePreset('all-time').start).toBeNull();
        expect(resolvePreset('all-time').end).toBeNull();
    });
});

describe('granularityForRange', () => {
    it('derives a granularity from the span', () => {
        expect(granularityForRange(null, null)).toBe('month');
        expect(
            granularityForRange(new Date(2026, 8, 1), new Date(2026, 8, 30)),
        ).toBe('day');
        expect(
            granularityForRange(new Date(2025, 8, 1), new Date(2026, 8, 30)),
        ).toBe('month');
        expect(
            granularityForRange(new Date(2019, 0, 1), new Date(2026, 8, 30)),
        ).toBe('year');
    });
});

describe('comparisons', () => {
    it('computes the previous period of equal length', () => {
        const previous = previousPeriod(
            new Date(2026, 8, 1),
            new Date(2026, 8, 30),
        );

        expect(toISODate(previous?.start ?? null)).toBe('2026-08-02');
        expect(toISODate(previous?.end ?? null)).toBe('2026-08-31');
    });

    it('computes the same period one year earlier', () => {
        const lastYear = samePeriodLastYear(
            new Date(2026, 8, 1),
            new Date(2026, 8, 30),
        );

        expect(toISODate(lastYear?.start ?? null)).toBe('2025-09-01');
        expect(toISODate(lastYear?.end ?? null)).toBe('2025-09-30');
    });

    it('matches concrete boundaries back to a preset', () => {
        expect(
            matchPreset(new Date(2026, 8, 1), new Date(2026, 8, 30), { now }),
        ).toBe('this-month');
    });
});

describe('ISO serialization', () => {
    it('round-trips a local date', () => {
        const iso = toISODate(new Date(2026, 0, 5));

        expect(iso).toBe('2026-01-05');
        expect(toISODate(parseISODate(iso))).toBe('2026-01-05');
    });

    it('rejects malformed input', () => {
        expect(parseISODate('not-a-date')).toBeNull();
        expect(parseISODate('')).toBeNull();
        expect(parseISODate(42)).toBeNull();
    });
});
