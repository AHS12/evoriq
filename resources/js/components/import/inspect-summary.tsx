import { Loader2 } from 'lucide-react';
import { InlineAlert } from '@/components/feedback/inline-alert';
import { useTranslation } from '@/hooks/use-translation';
import { formatNumber } from '@/lib/format';
import type { ImportPlan } from '@/types/import';

type Props = {
    plan: ImportPlan | null;
    loading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * PIPE-09 — the inline "checking your workspace" summary shown once a range is
 * chosen: how much work the plan implies before the user commits.
 */
export function InspectSummary({ plan, loading, error, onRetry }: Props) {
    const { t } = useTranslation();

    if (loading) {
        return (
            <div
                aria-live="polite"
                className="flex items-center gap-2 rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground"
            >
                <Loader2 aria-hidden className="size-4 animate-spin" />
                {t('Checking your workspace…')}
            </div>
        );
    }

    if (error) {
        return (
            <InlineAlert
                tone="warning"
                title={t('Could not estimate this range')}
                action={
                    <button
                        type="button"
                        onClick={onRetry}
                        className="text-xs font-medium underline underline-offset-4"
                    >
                        {t('Retry')}
                    </button>
                }
            >
                {error}
            </InlineAlert>
        );
    }

    if (plan === null) {
        return null;
    }

    const entities = new Set(
        plan.phases.flatMap((phase) =>
            phase.jobs.map((job) => job.entity_type),
        ),
    );

    const stats = [
        { label: t('Entities'), value: formatNumber(entities.size) },
        { label: t('Jobs'), value: formatNumber(plan.estimate.jobs) },
        {
            label: t('Partitions'),
            value: formatNumber(plan.estimate.partitions),
        },
        {
            label: t('Estimated API requests'),
            value: formatNumber(plan.estimate.requests),
        },
    ];

    return (
        <div className="rounded-lg border bg-card p-4">
            <p className="text-sm text-muted-foreground">
                {t('Discovered from your workspace')}
            </p>
            <dl className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {stats.map((stat) => (
                    <div key={stat.label}>
                        <dt className="text-xs text-muted-foreground">
                            {stat.label}
                        </dt>
                        <dd className="text-lg font-semibold tabular-nums">
                            {stat.value}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
