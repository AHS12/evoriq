import {
    granularityForRange,
    isComparisonMode,
    isPresetKey,
    matchPreset,
    parseISODate,
    resolvePreset,
    toISODate,
    type ComparisonRange,
    type DateRange,
} from '@/lib/date-range';

/**
 * Query-param names the range is serialized into. Consumers can remap them,
 * e.g. the audit log uses `date_from`/`date_to`.
 */
export type DateRangeParamNames = {
    start: string;
    end: string;
    period: string;
    compare: string;
    compareStart: string;
    compareEnd: string;
};

const DEFAULT_PARAMS: DateRangeParamNames = {
    start: 'start',
    end: 'end',
    period: 'period',
    compare: 'compare',
    compareStart: 'compare_start',
    compareEnd: 'compare_end',
};

export type UseDateRangeOptions = {
    params?: Partial<DateRangeParamNames>;
    /** Earliest data; used by the "All time" preset. */
    min?: Date;
    /** Latest data; used by the "All time" preset. */
    max?: Date;
};

/**
 * Reads a {@link DateRange} (and comparison) from Inertia filter props and
 * produces the query params to write back. Pair it with `useDataTableFilters`:
 *
 * ```tsx
 * const { range, comparison, toParams } = useDateRange(filters);
 * <DateRangePicker
 *   value={range}
 *   comparison={comparison}
 *   onApply={(next, compare) => apply(toParams(next, compare), true)}
 * />;
 * ```
 */
export function useDateRange(
    filters: Record<string, unknown>,
    options: UseDateRangeOptions = {},
) {
    const params: DateRangeParamNames = {
        ...DEFAULT_PARAMS,
        ...options.params,
    };

    return {
        range: readRange(filters, params, options),
        comparison: readComparison(filters, params),
        toParams: (
            range: DateRange,
            comparison?: ComparisonRange,
        ): Record<string, string | undefined> =>
            buildParams(range, comparison, params),
        isCustom: readRange(filters, params, options).preset === 'custom',
    };
}

function readRange(
    filters: Record<string, unknown>,
    params: DateRangeParamNames,
    options: UseDateRangeOptions,
): DateRange {
    const start = parseISODate(filters[params.start]);
    const end = parseISODate(filters[params.end]);
    const period = filters[params.period];

    if (start || end) {
        const preset = isPresetKey(period)
            ? period
            : matchPreset(start, end, options);

        return {
            preset,
            start,
            end,
            granularity: granularityForRange(start, end),
        };
    }

    const preset = isPresetKey(period) ? period : 'all-time';
    const { start: resolvedStart, end: resolvedEnd } = resolvePreset(
        preset,
        options,
    );

    return {
        preset,
        start: resolvedStart,
        end: resolvedEnd,
        granularity: granularityForRange(resolvedStart, resolvedEnd),
    };
}

function readComparison(
    filters: Record<string, unknown>,
    params: DateRangeParamNames,
): ComparisonRange {
    const mode = filters[params.compare];
    const resolvedMode = isComparisonMode(mode) ? mode : 'none';

    return {
        mode: resolvedMode,
        start:
            resolvedMode === 'custom'
                ? parseISODate(filters[params.compareStart])
                : null,
        end:
            resolvedMode === 'custom'
                ? parseISODate(filters[params.compareEnd])
                : null,
    };
}

function buildParams(
    range: DateRange,
    comparison: ComparisonRange | undefined,
    params: DateRangeParamNames,
): Record<string, string | undefined> {
    const output: Record<string, string | undefined> = {
        [params.period]: range.preset,
        [params.start]: toISODate(range.start),
        [params.end]: toISODate(range.end),
        [params.compare]:
            comparison && comparison.mode !== 'none'
                ? comparison.mode
                : undefined,
        [params.compareStart]: undefined,
        [params.compareEnd]: undefined,
    };

    if (comparison?.mode === 'custom') {
        output[params.compareStart] = toISODate(comparison.start);
        output[params.compareEnd] = toISODate(comparison.end);
    }

    return output;
}
