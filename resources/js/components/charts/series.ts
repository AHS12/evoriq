import type { ChartConfig } from '@/components/ui/chart';
import type { ChartSeries } from '@/components/charts/types';

/** Chart tokens declared in `resources/css/app.css` (`@theme`). */
const SERIES_COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
] as const;

/** The token colour for the series at `index` (cycles after five). */
export function seriesColor(index: number): string {
    return SERIES_COLORS[index % SERIES_COLORS.length];
}

/**
 * Derive a shadcn {@link ChartConfig} from a series list, assigning the chart
 * tokens in order unless a series declares its own colour.
 */
export function buildChartConfig(series: ChartSeries[]): ChartConfig {
    const config: ChartConfig = {};

    series.forEach((item, index) => {
        config[item.key] = {
            label: item.label,
            color: item.color ?? seriesColor(index),
        };
    });

    return config;
}
