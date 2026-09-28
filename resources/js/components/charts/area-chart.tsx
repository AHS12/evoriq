import {
    Area,
    AreaChart as RechartsAreaChart,
    CartesianGrid,
    XAxis,
    YAxis,
} from 'recharts';
import { ChartFrame } from '@/components/charts/chart-frame';
import { ChartValueTooltip } from '@/components/charts/chart-tooltip';
import { buildChartConfig } from '@/components/charts/series';
import type { ChartBaseProps } from '@/components/charts/types';
import {
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
} from '@/components/ui/chart';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { useTranslation } from '@/hooks/use-translation';

export function AreaChart({
    data,
    series,
    xKey,
    className,
    config,
    isLoading = false,
    emptyTitle,
    emptyDescription,
    valueFormatter,
    xTickFormatter,
    yTickFormatter,
    showLegend = true,
    showGrid = true,
    ariaLabel,
}: ChartBaseProps) {
    const { t } = useTranslation();
    const reducedMotion = useReducedMotion();
    const resolvedConfig = config ?? buildChartConfig(series);

    return (
        <ChartFrame
            config={resolvedConfig}
            data={data}
            isLoading={isLoading}
            emptyTitle={emptyTitle ?? t('No data yet')}
            emptyDescription={emptyDescription}
            ariaLabel={ariaLabel ?? series.map((item) => item.label).join(', ')}
            className={className}
        >
            <RechartsAreaChart
                data={data}
                margin={{ top: 8, right: 12, bottom: 0, left: 0 }}
            >
                {showGrid && (
                    <CartesianGrid vertical={false} strokeDasharray="3 3" />
                )}
                <XAxis
                    dataKey={xKey}
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    tickFormatter={xTickFormatter}
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                    width={48}
                    tickFormatter={yTickFormatter}
                />
                <ChartTooltip
                    cursor={false}
                    content={
                        <ChartValueTooltip
                            config={resolvedConfig}
                            valueFormatter={valueFormatter}
                        />
                    }
                />
                {showLegend && <ChartLegend content={<ChartLegendContent />} />}
                {series.map((item) => (
                    <Area
                        key={item.key}
                        dataKey={item.key}
                        type="monotone"
                        stackId={item.stackId}
                        stroke={`var(--color-${item.key})`}
                        fill={`var(--color-${item.key})`}
                        fillOpacity={0.25}
                        strokeWidth={2}
                        isAnimationActive={!reducedMotion}
                    />
                ))}
            </RechartsAreaChart>
        </ChartFrame>
    );
}
