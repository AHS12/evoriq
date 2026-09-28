import {
    isActiveStatus,
    relativeTime,
} from '@/components/data-processing/job-utils';
import { JobStatusBadge } from '@/components/data-processing/job-status-badge';
import { RunProgress } from '@/components/data-processing/run-progress';
import { InlineAlert } from '@/components/feedback/inline-alert';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

/**
 * PIPE-05 — the run hero: identity, status, the big progress block and the
 * "you can leave" promise (or a staleness warning).
 */
export function RunHero({ run }: Props) {
    const { t } = useTranslation();
    const active = isActiveStatus(run.status);

    return (
        <section className="space-y-4 rounded-xl border bg-card p-4 sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="truncate text-lg font-semibold tracking-tight">
                            {run.name}
                        </h1>
                        <JobStatusBadge
                            status={run.status}
                            label={run.status_label}
                        />
                        {run.attempts.current > 1 && (
                            <Badge variant="outline">
                                {run.attempts.label}
                            </Badge>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {t('Started :time', {
                            time: relativeTime(
                                run.started_at ?? run.created_at,
                            ),
                        })}
                        {run.owner ? ` · ${run.owner}` : ''}
                    </p>
                </div>
            </div>

            <RunProgress run={run} />

            {active && !run.timing.stale && (
                <InlineAlert tone="info">
                    {t(
                        'You can leave this page — the job keeps running in the background.',
                    )}
                </InlineAlert>
            )}

            {active && run.timing.stale && (
                <InlineAlert tone="warning" title={t('No recent progress')}>
                    {t(
                        'This run has not reported progress recently. It may be stalled.',
                    )}
                </InlineAlert>
            )}
        </section>
    );
}
