import { Link } from '@inertiajs/react';
import { CheckCircle2, LayoutDashboard, ListTree } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDuration, formatNumber } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as importIndex } from '@/routes/import';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

/**
 * PIPE-09 step 5 — the completion summary with a clear next step. Rendered only
 * for a finished, successful run.
 */
export function ImportComplete({ run }: Props) {
    const { t } = useTranslation();

    const totals = [
        { label: t('Records created'), value: run.counts.created ?? 0 },
        { label: t('Records updated'), value: run.counts.updated ?? 0 },
    ];

    return (
        <section
            data-slot="import-complete"
            className="space-y-4 rounded-xl border border-success/30 bg-success/5 p-6 text-center"
        >
            <span className="mx-auto flex size-12 items-center justify-center rounded-full bg-success/15">
                <CheckCircle2 aria-hidden className="size-6 text-success" />
            </span>
            <div className="space-y-1">
                <h2 className="text-lg font-semibold">
                    {t('Your history is imported')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t(
                        'The data is ready to explore in dashboards and reports.',
                    )}
                </p>
            </div>

            <dl className="mx-auto grid max-w-sm grid-cols-2 gap-3">
                {totals.map((total) => (
                    <div key={total.label}>
                        <dt className="text-xs text-muted-foreground">
                            {total.label}
                        </dt>
                        <dd className="text-lg font-semibold tabular-nums">
                            {formatNumber(total.value)}
                        </dd>
                    </div>
                ))}
            </dl>

            {run.timing.duration_ms != null && (
                <p className="text-xs text-muted-foreground">
                    {t('Duration')} ·{' '}
                    {formatDuration(run.timing.duration_ms / 1000)}
                </p>
            )}

            <div className="flex flex-wrap justify-center gap-2">
                <Button asChild>
                    <Link href={dashboard()}>
                        <LayoutDashboard className="size-4" />
                        {t('Open dashboard')}
                    </Link>
                </Button>
                <Button variant="outline" asChild>
                    <Link href={importIndex()}>
                        <ListTree className="size-4" />
                        {t('Run another import')}
                    </Link>
                </Button>
            </div>
        </section>
    );
}
