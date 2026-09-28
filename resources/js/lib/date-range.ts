import { appLocale } from '@/lib/locale';

/**
 * Pure date-range math for the shared period picker (FND-03).
 *
 * All ranges are **date-only** (local midnight boundaries, inclusive). Timezone
 * conversion is a later analytics concern — these helpers only resolve which
 * calendar days a preset or comparison refers to. Serialization uses local
 * `YYYY-MM-DD` (never UTC) so a picked day never shifts by one.
 */

export type DateRangePresetKey =
    | 'today'
    | 'yesterday'
    | 'this-week'
    | 'last-week'
    | 'this-month'
    | 'last-month'
    | 'this-quarter'
    | 'last-quarter'
    | 'this-year'
    | 'last-year'
    | 'last-2-years'
    | 'last-5-years'
    | 'all-time'
    | 'custom';

export type DateRangeGranularity =
    | 'day'
    | 'week'
    | 'month'
    | 'quarter'
    | 'year';

export type DateRange = {
    preset: DateRangePresetKey;
    start: Date | null;
    end: Date | null;
    granularity: DateRangeGranularity;
};

export type ComparisonMode =
    | 'none'
    | 'previous-period'
    | 'same-period-last-year'
    | 'custom';

export type ComparisonRange = {
    mode: ComparisonMode;
    start: Date | null;
    end: Date | null;
};

export type ResolvedRange = {
    start: Date | null;
    end: Date | null;
};

export type PresetOptions = {
    /** Reference "now" (defaults to the real clock; injectable for tests). */
    now?: Date;
    /** Earliest available data, used by "All time". */
    min?: Date | null;
    /** Latest available data, used by "All time". */
    max?: Date | null;
};

const MS_PER_DAY = 86_400_000;

const MATCHABLE_PRESETS: DateRangePresetKey[] = [
    'today',
    'yesterday',
    'this-week',
    'last-week',
    'this-month',
    'last-month',
    'this-quarter',
    'last-quarter',
    'this-year',
    'last-year',
    'last-2-years',
    'last-5-years',
];

export function startOfDay(value: Date): Date {
    const date = new Date(value);
    date.setHours(0, 0, 0, 0);

    return date;
}

export function addDays(value: Date, days: number): Date {
    const date = startOfDay(value);
    date.setDate(date.getDate() + days);

    return date;
}

export function addMonths(value: Date, months: number): Date {
    const date = startOfDay(value);
    date.setMonth(date.getMonth() + months);

    return date;
}

export function addYears(value: Date, years: number): Date {
    const date = startOfDay(value);
    date.setFullYear(date.getFullYear() + years);

    return date;
}

export function startOfWeek(value: Date): Date {
    const date = startOfDay(value);

    // ISO weeks start on Monday.
    return addDays(date, -((date.getDay() + 6) % 7));
}

export function startOfMonth(value: Date): Date {
    return new Date(value.getFullYear(), value.getMonth(), 1);
}

export function endOfMonth(value: Date): Date {
    return new Date(value.getFullYear(), value.getMonth() + 1, 0);
}

export function startOfQuarter(value: Date): Date {
    const month = Math.floor(value.getMonth() / 3) * 3;

    return new Date(value.getFullYear(), month, 1);
}

export function endOfQuarter(value: Date): Date {
    const start = startOfQuarter(value);

    return new Date(start.getFullYear(), start.getMonth() + 3, 0);
}

export function startOfYear(value: Date): Date {
    return new Date(value.getFullYear(), 0, 1);
}

export function endOfYear(value: Date): Date {
    return new Date(value.getFullYear(), 11, 31);
}

export function diffInDays(start: Date, end: Date): number {
    return Math.round(
        (startOfDay(end).getTime() - startOfDay(start).getTime()) / MS_PER_DAY,
    );
}

/**
 * Resolve a preset to concrete (inclusive) date boundaries. `all-time` falls
 * back to the provided `min`/`max`, or an open range when they are unknown.
 */
export function resolvePreset(
    preset: DateRangePresetKey,
    options: PresetOptions = {},
): ResolvedRange {
    const now = startOfDay(options.now ?? new Date());

    switch (preset) {
        case 'today':
            return { start: now, end: now };
        case 'yesterday': {
            const day = addDays(now, -1);

            return { start: day, end: day };
        }
        case 'this-week': {
            const start = startOfWeek(now);

            return { start, end: addDays(start, 6) };
        }
        case 'last-week': {
            const start = addDays(startOfWeek(now), -7);

            return { start, end: addDays(start, 6) };
        }
        case 'this-month':
            return { start: startOfMonth(now), end: endOfMonth(now) };
        case 'last-month': {
            const start = addMonths(startOfMonth(now), -1);

            return { start, end: endOfMonth(start) };
        }
        case 'this-quarter':
            return { start: startOfQuarter(now), end: endOfQuarter(now) };
        case 'last-quarter': {
            const start = addMonths(startOfQuarter(now), -3);

            return { start, end: endOfQuarter(start) };
        }
        case 'this-year':
            return { start: startOfYear(now), end: endOfYear(now) };
        case 'last-year': {
            const start = addYears(startOfYear(now), -1);

            return { start, end: endOfYear(start) };
        }
        case 'last-2-years':
            return { start: addYears(now, -2), end: now };
        case 'last-5-years':
            return { start: addYears(now, -5), end: now };
        case 'all-time':
            return {
                start: options.min ? startOfDay(options.min) : null,
                end: options.max ? startOfDay(options.max) : null,
            };
        case 'custom':
        default:
            return { start: null, end: null };
    }
}

/**
 * A charting hint derived from the span: daily up to ~3 months, monthly up to
 * ~3 years, otherwise yearly.
 */
export function granularityForRange(
    start: Date | null,
    end: Date | null,
): DateRangeGranularity {
    if (!start || !end) {
        return 'month';
    }

    const days = diffInDays(start, end) + 1;

    if (days <= 92) {
        return 'day';
    }

    if (days <= 1096) {
        return 'month';
    }

    return 'year';
}

/** Resolve a preset into a full {@link DateRange} value object. */
export function buildDateRange(
    preset: DateRangePresetKey,
    options: PresetOptions = {},
): DateRange {
    const { start, end } = resolvePreset(preset, options);

    return {
        preset,
        start,
        end,
        granularity: granularityForRange(start, end),
    };
}

/** The equivalent period immediately before the given range. */
export function previousPeriod(
    start: Date | null,
    end: Date | null,
): ResolvedRange | null {
    if (!start || !end) {
        return null;
    }

    const days = diffInDays(start, end) + 1;
    const previousEnd = addDays(start, -1);
    const previousStart = addDays(previousEnd, -(days - 1));

    return { start: previousStart, end: previousEnd };
}

/** The same calendar range one year earlier. */
export function samePeriodLastYear(
    start: Date | null,
    end: Date | null,
): ResolvedRange | null {
    if (!start || !end) {
        return null;
    }

    return { start: addYears(start, -1), end: addYears(end, -1) };
}

/** Resolve a comparison mode against the primary range. */
export function resolveComparison(
    mode: ComparisonMode,
    range: ResolvedRange,
    custom: ResolvedRange = { start: null, end: null },
): ComparisonRange {
    if (mode === 'none') {
        return { mode, start: null, end: null };
    }

    if (mode === 'custom') {
        return { mode, start: custom.start, end: custom.end };
    }

    const resolved =
        mode === 'previous-period'
            ? previousPeriod(range.start, range.end)
            : samePeriodLastYear(range.start, range.end);

    return { mode, start: resolved?.start ?? null, end: resolved?.end ?? null };
}

/** Find the preset matching concrete boundaries, else `custom` / `all-time`. */
export function matchPreset(
    start: Date | null,
    end: Date | null,
    options: PresetOptions = {},
): DateRangePresetKey {
    if (!start && !end) {
        return 'all-time';
    }

    if (!start || !end) {
        return 'custom';
    }

    const targetStart = startOfDay(start).getTime();
    const targetEnd = startOfDay(end).getTime();

    for (const preset of MATCHABLE_PRESETS) {
        const resolved = resolvePreset(preset, options);

        if (
            resolved.start &&
            resolved.end &&
            resolved.start.getTime() === targetStart &&
            resolved.end.getTime() === targetEnd
        ) {
            return preset;
        }
    }

    return 'custom';
}

export function isPresetKey(value: unknown): value is DateRangePresetKey {
    return (
        typeof value === 'string' &&
        MATCHABLE_PRESETS.concat(['all-time', 'custom']).includes(
            value as DateRangePresetKey,
        )
    );
}

export function isComparisonMode(value: unknown): value is ComparisonMode {
    return (
        value === 'none' ||
        value === 'previous-period' ||
        value === 'same-period-last-year' ||
        value === 'custom'
    );
}

/** Serialize a date to local `YYYY-MM-DD` (never UTC). */
export function toISODate(value: Date | null): string | undefined {
    if (!value) {
        return undefined;
    }

    const date = startOfDay(value);
    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** Parse a local `YYYY-MM-DD` string, or return null when invalid. */
export function parseISODate(value: unknown): Date | null {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return Number.isNaN(date.getTime()) ? null : startOfDay(date);
}

function formatLongDate(value: Date, locale: string): string {
    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    }).format(value);
}

/**
 * Human range label, e.g. `Sep 1 – Sep 30, 2026`. Returns an empty string for
 * an open range so the caller can substitute a translated preset label.
 */
export function formatRangeLabel(
    start: Date | null,
    end: Date | null,
    locale: string = appLocale(),
): string {
    if (!start && !end) {
        return '';
    }

    if (start && !end) {
        return formatLongDate(start, locale);
    }

    if (!start && end) {
        return formatLongDate(end, locale);
    }

    const from = start as Date;
    const to = end as Date;

    if (from.getTime() === to.getTime()) {
        return formatLongDate(from, locale);
    }

    if (from.getFullYear() === to.getFullYear()) {
        const shortFrom = new Intl.DateTimeFormat(locale, {
            month: 'short',
            day: 'numeric',
        }).format(from);

        return `${shortFrom} – ${formatLongDate(to, locale)}`;
    }

    return `${formatLongDate(from, locale)} – ${formatLongDate(to, locale)}`;
}
