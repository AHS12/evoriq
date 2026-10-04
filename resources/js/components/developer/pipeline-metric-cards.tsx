import { useTranslation } from '@/hooks/use-translation';
import { formatDuration, formatNumber } from '@/lib/format';
import { Card, CardContent } from '@/components/ui/card';
import type { PipelineMetrics } from '@/types';

type Props = {
    metrics: PipelineMetrics;
};

export function PipelineMetricCards({ metrics }: Props) {
    const { t } = useTranslation();

    const duration = (ms: number | null): string =>
        ms === null ? '—' : formatDuration(ms / 1000);

    const cards: { key: string; label: string; value: string; hint: string }[] =
        [
            {
                key: 'active',
                label: t('Active now'),
                value: formatNumber(metrics.active_now),
                hint: t(':count queued', { count: metrics.queued }),
            },
            {
                key: 'today',
                label: t('Runs today'),
                value: formatNumber(metrics.runs_today),
                hint: t(':count failures (24h)', {
                    count: metrics.failures_24h,
                }),
            },
            {
                key: 'success',
                label: t('Success rate (7d)'),
                value:
                    metrics.success_rate_7d === null
                        ? '—'
                        : `${metrics.success_rate_7d}%`,
                hint: t(':done done · :failed failed', {
                    done: metrics.completed_7d,
                    failed: metrics.failed_7d,
                }),
            },
            {
                key: 'records',
                label: t('Records (7d)'),
                value: formatNumber(metrics.records_7d),
                hint: t(':count stale', { count: metrics.stale }),
            },
            {
                key: 'duration',
                label: t('Average duration'),
                value: duration(metrics.avg_duration_ms),
                hint: t('p95 :value', {
                    value: duration(metrics.p95_duration_ms),
                }),
            },
            {
                key: 'reasons',
                label: t('Top failure reason'),
                value: metrics.failure_reasons[0]?.label ?? '—',
                hint:
                    metrics.failure_reasons[0] === undefined
                        ? t('No recent failures')
                        : t(':count in 7 days', {
                              count: metrics.failure_reasons[0].count,
                          }),
            },
        ];

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {cards.map((card) => (
                <Card key={card.key}>
                    <CardContent className="space-y-1 py-5">
                        <p className="text-sm text-muted-foreground">
                            {card.label}
                        </p>
                        <p className="text-2xl font-semibold tracking-tight">
                            {card.value}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {card.hint}
                        </p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
