import { ChartCard } from '@/components/charts/chart-card';
import { DonutChart } from '@/components/charts/donut-chart';
import { LineChart } from '@/components/charts/line-chart';
import { formatHours, formatMonth, formatNumber } from '@/lib/format';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Static, sample-only analytics charts for the dashboard shell. Real data
 * arrives with the analytics engine (ANA) and replaces this preview.
 */

function localIsoMonth(offset: number): string {
    const now = new Date();
    const date = new Date(now.getFullYear(), now.getMonth() - offset, 1);
    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-01`;
}

const MONTHLY_HOURS = Array.from({ length: 12 }, (_, index) => ({
    date: localIsoMonth(11 - index),
    billable: 96 + ((index * 37) % 72),
    nonBillable: 22 + ((index * 19) % 34),
}));

const PROJECT_HOURS = [
    { project: 'Website redesign', hours: 412 },
    { project: 'Mobile app', hours: 281 },
    { project: 'Internal tools', hours: 193 },
    { project: 'Support', hours: 120 },
];

export function AnalyticsPreview() {
    const { t } = useTranslation();

    const series = [
        { key: 'billable', label: t('Billable') },
        { key: 'nonBillable', label: t('Non-billable') },
    ];

    return (
        <section className="space-y-3">
            <div>
                <h2 className="text-sm font-medium">{t('Example charts')}</h2>
                <p className="text-sm text-muted-foreground">
                    {t('Sample data. Connect Clockify to see your own.')}
                </p>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <ChartCard
                    title={t('Hours tracked')}
                    description={t('Last 12 months')}
                    className="lg:col-span-2"
                >
                    <LineChart
                        data={MONTHLY_HOURS}
                        series={series}
                        xKey="date"
                        className="aspect-auto h-72 w-full"
                        xTickFormatter={(value) => formatMonth(value)}
                        yTickFormatter={(value) => formatNumber(Number(value))}
                        valueFormatter={(value) =>
                            formatHours(value, { from: 'hours' })
                        }
                    />
                </ChartCard>

                <ChartCard
                    title={t('Hours by project')}
                    description={t('Sample data')}
                >
                    <DonutChart
                        data={PROJECT_HOURS}
                        nameKey="project"
                        valueKey="hours"
                        className="aspect-auto h-72 w-full"
                        valueFormatter={(value) =>
                            formatHours(value, { from: 'hours' })
                        }
                    />
                </ChartCard>
            </div>
        </section>
    );
}
