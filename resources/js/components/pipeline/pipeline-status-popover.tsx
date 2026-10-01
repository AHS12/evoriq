import { Link } from '@inertiajs/react';
import { ArrowRight, Database, Gauge, TriangleAlert } from 'lucide-react';
import { JobStatusBadge } from '@/components/data-processing/job-status-badge';
import { JobTypeIcon } from '@/components/data-processing/job-type-icon';
import { LiveDot } from '@/components/feedback/live-dot';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import { formatDuration, formatNumber } from '@/lib/format';
import { index as activityIndex, show } from '@/routes/activity';
import type { PipelineStatus, PipelineStatusRun } from '@/types';

type Props = {
    status: PipelineStatus;
    live: boolean;
    onToggleLive: () => void;
    /** Called when a link is followed, so the popover can close. */
    onNavigate: () => void;
};

function RunProgress({ run }: { run: PipelineStatusRun }) {
    return (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
            {run.indeterminate ? (
                <div className="h-full w-1/3 rounded-full bg-primary motion-safe:animate-pulse" />
            ) : (
                <div
                    className="h-full rounded-full bg-primary transition-all"
                    style={{
                        width: `${Math.min(100, Math.max(0, run.percentage))}%`,
                    }}
                />
            )}
        </div>
    );
}

/**
 * PIPE-08 — the global indicator popover: active runs with live mini progress,
 * a recent-failures entry point, and the reserved freshness / API-budget slots.
 */
export function PipelineStatusPopover({
    status,
    live,
    onToggleLive,
    onNavigate,
}: Props) {
    const { t } = useTranslation();
    const failedHref = `${activityIndex.url()}?status=failed`;

    return (
        <div data-slot="pipeline-status-popover">
            <div className="flex items-center justify-between gap-2 px-4 py-3">
                <div className="min-w-0">
                    <p className="text-sm font-medium">{t('Pipeline')}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {t(':count running', { count: status.active_count })}
                        {status.queued_count > 0
                            ? ` · ${t(':count queued', { count: status.queued_count })}`
                            : ''}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={onToggleLive}
                    aria-label={
                        live
                            ? t('Pause live updates')
                            : t('Resume live updates')
                    }
                    className="gap-1.5 text-muted-foreground"
                >
                    <LiveDot active={live} />
                    {live ? t('Live') : t('Paused')}
                </Button>
            </div>

            <Separator />

            {status.runs.length === 0 ? (
                <p className="px-4 py-6 text-center text-xs text-muted-foreground">
                    {t('No background jobs are running.')}
                </p>
            ) : (
                <ul className="max-h-[50vh] divide-y overflow-y-auto">
                    {status.runs.map((run) => (
                        <li key={run.id}>
                            <Link
                                href={show.url(run.id)}
                                onClick={onNavigate}
                                className="flex items-start gap-3 px-4 py-3 transition-smooth-fast hover:bg-muted/40"
                            >
                                <JobTypeIcon icon={run.entity_icon} />
                                <div className="min-w-0 flex-1 space-y-1.5">
                                    <div className="flex items-center gap-2">
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                            {run.name}
                                        </span>
                                        <JobStatusBadge
                                            status={run.status}
                                            label={run.status_label}
                                        />
                                    </div>
                                    <RunProgress run={run} />
                                    <div className="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                                        <span className="truncate">
                                            {run.stage ?? run.status_label}
                                        </span>
                                        {run.eta_seconds != null && (
                                            <span className="shrink-0">
                                                {t('~:count left', {
                                                    count: formatDuration(
                                                        run.eta_seconds,
                                                    ),
                                                })}
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {status.failed_recent > 0 && (
                <>
                    <Separator />
                    <Link
                        href={failedHref}
                        onClick={onNavigate}
                        className="flex items-center gap-2 px-4 py-2.5 text-sm text-destructive transition-smooth-fast hover:bg-destructive/5"
                    >
                        <TriangleAlert className="size-4 shrink-0" />
                        <span className="flex-1">
                            {t(':count recent failures', {
                                count: status.failed_recent,
                            })}
                        </span>
                        <ArrowRight className="size-4 shrink-0" />
                    </Link>
                </>
            )}

            {status.freshness !== null && (
                <>
                    <Separator />
                    <div className="flex items-center gap-2 px-4 py-2.5 text-sm">
                        <Database className="size-4 shrink-0 text-muted-foreground" />
                        <span className="flex-1 text-muted-foreground">
                            {t('Data through :date', {
                                date: status.freshness.data_through ?? '—',
                            })}
                        </span>
                    </div>
                </>
            )}

            {status.api_budget !== null && (
                <>
                    <Separator />
                    <div className="flex items-center gap-2 px-4 py-2.5 text-sm">
                        <Gauge className="size-4 shrink-0 text-muted-foreground" />
                        <span className="flex-1 text-muted-foreground">
                            {t('API :used/:limit', {
                                used: formatNumber(status.api_budget.used),
                                limit: formatNumber(status.api_budget.limit),
                            })}
                        </span>
                    </div>
                </>
            )}

            <Separator />
            <div className="p-2">
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="w-full justify-center"
                >
                    <Link href={activityIndex()} onClick={onNavigate}>
                        {t('View all activity')}
                    </Link>
                </Button>
            </div>
        </div>
    );
}
