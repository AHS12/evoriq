import type { ChartConfig } from '@/components/ui/chart';

/** A single row of chart data. Series keys map to numeric (or null) fields. */
export type ChartDatum = Record<string, string | number | null | undefined>;

export type ChartSeries = {
    /** The datum key plotted for this series. */
    key: string;
    /** Human label (translated) shown in tooltips and legend. */
    label: string;
    /** Explicit colour; defaults to the next `--chart-N` token. */
    color?: string;
    /** Shared id to stack series (bars/areas) together. */
    stackId?: string;
};

export type ChartValueFormatter = (value: number, seriesKey?: string) => string;
export type ChartTickFormatter = (value: string | number) => string;

export type ChartBaseProps = {
    data: ChartDatum[];
    series: ChartSeries[];
    /** The x-axis datum key (e.g. a date or label). */
    xKey: string;
    className?: string;
    /** Override the derived {@link ChartConfig}. */
    config?: ChartConfig;
    isLoading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    /** Formats tooltip values, usually delegating to `lib/format.ts`. */
    valueFormatter?: ChartValueFormatter;
    /** Formats x-axis ticks, usually delegating to `lib/format.ts`. */
    xTickFormatter?: ChartTickFormatter;
    /** Formats y-axis ticks, usually delegating to `lib/format.ts`. */
    yTickFormatter?: ChartTickFormatter;
    showLegend?: boolean;
    showGrid?: boolean;
    /** Accessible name; defaults to the series labels. */
    ariaLabel?: string;
};
