import { Cell, Legend, Pie, PieChart as RechartsPieChart } from 'recharts';
import { ChartFrame } from '@/components/charts/chart-frame';
import { ChartValueTooltip } from '@/components/charts/chart-tooltip';
import { seriesColor } from '@/components/charts/series';
import type {
    ChartDatum,
    ChartValueFormatter,
} from '@/components/charts/types';
import { ChartTooltip, type ChartConfig } from '@/components/ui/chart';
import { useReducedMotion } from '@/hooks/use-reduced-motion';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    data: ChartDatum[];
    /** Datum key holding each slice's name. */
    nameKey: string;
    /** Datum key holding each slice's value. */
    valueKey: string;
    className?: string;
    isLoading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    valueFormatter?: ChartValueFormatter;
    showLegend?: boolean;
    ariaLabel?: string;
};

export function DonutChart({
    data,
    nameKey,
    valueKey,
    className,
    isLoading = false,
    emptyTitle,
    emptyDescription,
    valueFormatter,
    showLegend = true,
    ariaLabel,
}: Props) {
    const { t } = useTranslation();
    const reducedMotion = useReducedMotion();

    const config: ChartConfig = {};
    data.forEach((row, index) => {
        const key = String(row[nameKey] ?? index);
        config[key] = { label: key, color: seriesColor(index) };
    });

    const label =
        ariaLabel ?? data.map((row) => String(row[nameKey] ?? '')).join(', ');

    return (
        <ChartFrame
            config={config}
            data={data}
            isLoading={isLoading}
            emptyTitle={emptyTitle ?? t('No data yet')}
            emptyDescription={emptyDescription}
            ariaLabel={label}
            className={className}
        >
            <RechartsPieChart>
                <ChartTooltip
                    cursor={false}
                    content={
                        <ChartValueTooltip
                            config={config}
                            valueFormatter={valueFormatter}
                        />
                    }
                />
                {showLegend && <Legend />}
                <Pie
                    data={data}
                    dataKey={valueKey}
                    nameKey={nameKey}
                    innerRadius="60%"
                    outerRadius="82%"
                    paddingAngle={2}
                    stroke="var(--background)"
                    isAnimationActive={!reducedMotion}
                >
                    {data.map((row, index) => (
                        <Cell
                            key={String(row[nameKey] ?? index)}
                            fill={seriesColor(index)}
                        />
                    ))}
                </Pie>
            </RechartsPieChart>
        </ChartFrame>
    );
}
