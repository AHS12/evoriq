import { CircleAlert } from 'lucide-react';
import { isActiveStatus } from '@/components/data-processing/job-utils';
import { formatDuration, formatNumber } from '@/lib/format';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
    /** Whether the viewer may see the raw failure message. */
    canManage: boolean;
};

/**
 * PIPE-05 — the terminal summary for a finished run (completed/failed/
 * cancelled). Active runs render nothing.
 */
export function RunTerminalSummary({ run, canManage }: Props) {
    const { t } = useTranslation();

    if (isActiveStatus(run.status)) {
        return null;
    }

    if (run.status === 'completed') {
        const counts = [
            {
                label: t('Created'),
                value: run.counts.created ?? run.success_count,
            },
            { label: t('Updated'), value: run.counts.updated },
            {
                label: t('Failed'),
                value: run.counts.failed ?? run.error_count,
            },
            { label: t('Skipped'), value: run.counts.skipped },
        ];

        return (
            <section
                data-slot="run-terminal-summary"
                className="space-y-3 rounded-xl border border-success/30 bg-success/5 p-4"
            >
                <h2 className="text-sm font-medium">{t('Run completed')}</h2>
                <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {counts.map((count) => (
                        <div key={count.label}>
                            <dt className="text-xs text-muted-foreground">
                                {count.label}
                            </dt>
                            <dd className="text-lg font-semibold tabular-nums">
                                {formatNumber(count.value)}
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
            </section>
        );
    }

    if (run.status === 'failed') {
        const failure = run.failure;

        return (
            <section
                data-slot="run-terminal-summary"
                className="space-y-3 rounded-xl border border-destructive/30 bg-destructive/5 p-4"
            >
                <div className="flex items-center gap-2">
                    <CircleAlert
                        aria-hidden
                        className="size-4 text-destructive"
                    />
                    <h2 className="text-sm font-medium">
                        {failure ? t(failure.label) : t('Run failed')}
                    </h2>
                </div>

                {failure?.hint && (
                    <p className="text-sm text-muted-foreground">
                        {t(failure.hint)}
                    </p>
                )}

                {run.type === 'import' && (
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'Re-running an import updates existing records instead of creating duplicates.',
                        )}
                    </p>
                )}

                {canManage && run.error_message && (
                    <details className="group">
                        <summary className="cursor-pointer text-xs text-muted-foreground select-none">
                            {t('Show details')}
                        </summary>
                        <pre className="mt-2 overflow-x-auto rounded-md bg-muted p-2 text-xs whitespace-pre-wrap text-muted-foreground">
                            {run.error_message}
                        </pre>
                    </details>
                )}
            </section>
        );
    }

    return (
        <section
            data-slot="run-terminal-summary"
            className="rounded-xl border bg-muted/30 p-4"
        >
            <h2 className="text-sm font-medium text-muted-foreground">
                {t('Run cancelled')}
            </h2>
            <p className="mt-1 text-xs text-muted-foreground">
                {t(
                    'This run was cancelled. You can start it again from the action bar.',
                )}
            </p>
        </section>
    );
}
