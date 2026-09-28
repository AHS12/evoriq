import { useState, type ReactNode } from 'react';
import { Download } from 'lucide-react';
import {
    formatBytes,
    isActiveStatus,
} from '@/components/data-processing/job-utils';
import { RunAdvanced } from '@/components/data-processing/run-advanced';
import { RunArtifacts } from '@/components/data-processing/run-artifacts';
import { RunIssues } from '@/components/data-processing/run-issues';
import { RunParameters } from '@/components/data-processing/run-parameters';
import { RunProgress } from '@/components/data-processing/run-progress';
import { StageLanes } from '@/components/data-processing/stage-lanes';
import { Timeline } from '@/components/timeline/timeline';
import {
    TimelineContent,
    TimelineItem,
} from '@/components/timeline/timeline-item';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatNumber } from '@/lib/format';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob, JobArtifact, JobTimelineEvent } from '@/types';

export type InspectorTab =
    | 'timeline'
    | 'overview'
    | 'issues'
    | 'artifacts'
    | 'parameters'
    | 'advanced';

type Props = {
    run: DataProcessingJob;
    /** Loaded events, used by the Overview stage lanes. */
    events?: JobTimelineEvent[];
    /** The live timeline node (run page only). */
    timeline?: ReactNode;
    variant?: 'page' | 'sheet';
    value?: InspectorTab;
    onValueChange?: (tab: InspectorTab) => void;
};

function InspectorOverview({
    run,
    events,
}: {
    run: DataProcessingJob;
    events: JobTimelineEvent[];
}) {
    const { t } = useTranslation();

    const counts = [
        { label: t('Created'), value: run.counts.created ?? run.success_count },
        { label: t('Updated'), value: run.counts.updated },
        { label: t('Failed'), value: run.counts.failed ?? run.error_count },
        { label: t('Skipped'), value: run.counts.skipped },
    ];

    const artifact = run.artifacts.find(
        (candidate): candidate is JobArtifact & { download_url: string } =>
            candidate.downloadable &&
            candidate.download_url !== null &&
            candidate.download_url !== undefined,
    );

    return (
        <div className="space-y-5">
            {isActiveStatus(run.status) && <RunProgress run={run} />}

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

            {artifact && (
                <div
                    data-slot="overview-artifact"
                    className="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 p-3"
                >
                    <div className="min-w-0">
                        <p className="truncate text-sm font-medium">
                            {artifact.file_name ?? artifact.label}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatBytes(artifact.size)}
                        </p>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <a href={artifact.download_url}>
                            <Download className="size-4" />
                            {t('Download')}
                        </a>
                    </Button>
                </div>
            )}

            <section className="space-y-2">
                <Timeline density="compact" aria-label={t('Timeline')}>
                    {run.created_at && (
                        <TimelineItem tone="neutral" time={run.created_at}>
                            <TimelineContent title={t('Created')} />
                        </TimelineItem>
                    )}
                    {run.started_at && (
                        <TimelineItem
                            tone="info"
                            time={run.started_at}
                            running={run.status === 'processing'}
                            durationMs={
                                run.status === 'processing'
                                    ? run.progress.elapsed_seconds * 1000
                                    : null
                            }
                        >
                            <TimelineContent title={t('Started')} />
                        </TimelineItem>
                    )}
                    {run.completed_at && (
                        <TimelineItem
                            tone={run.status === 'failed' ? 'error' : 'success'}
                            time={run.completed_at}
                            durationMs={run.timing.duration_ms}
                        >
                            <TimelineContent
                                title={
                                    run.status === 'failed'
                                        ? t('Failed')
                                        : t('Finished')
                                }
                            />
                        </TimelineItem>
                    )}
                </Timeline>
            </section>

            <StageLanes run={run} events={events} />
        </div>
    );
}

/**
 * PIPE-06 — the shared run inspector.
 *
 * The Job activity sheet renders it with `variant="sheet"` (Overview first);
 * the run page renders it with `variant="page"` and passes the live timeline as
 * the default tab. Issues/Artifacts only appear when they exist, and Advanced
 * only when the backend allowed it.
 */
export function RunInspector({
    run,
    events = [],
    timeline,
    variant = 'page',
    value,
    onValueChange,
}: Props) {
    const { t } = useTranslation();

    const showTimeline = variant === 'page' && Boolean(timeline);
    const defaultTab: InspectorTab = showTimeline ? 'timeline' : 'overview';

    const [internal, setInternal] = useState<InspectorTab>(defaultTab);
    const active = value ?? internal;

    const change = (next: string): void => {
        const tab = next as InspectorTab;

        if (value === undefined) {
            setInternal(tab);
        }

        onValueChange?.(tab);
    };

    return (
        <Tabs value={active} onValueChange={change} className="gap-4">
            <TabsList className="flex-wrap">
                {showTimeline && (
                    <TabsTrigger value="timeline">{t('Timeline')}</TabsTrigger>
                )}
                {!showTimeline && (
                    <TabsTrigger value="overview">{t('Overview')}</TabsTrigger>
                )}
                {run.issues.length > 0 && (
                    <TabsTrigger value="issues">{t('Issues')}</TabsTrigger>
                )}
                {run.artifacts.length > 0 && (
                    <TabsTrigger value="artifacts">
                        {t('Artifacts')}
                    </TabsTrigger>
                )}
                <TabsTrigger value="parameters">{t('Parameters')}</TabsTrigger>
                {variant === 'page' && run.advanced !== null && (
                    <TabsTrigger value="advanced">{t('Advanced')}</TabsTrigger>
                )}
            </TabsList>

            {showTimeline && (
                <TabsContent value="timeline">{timeline}</TabsContent>
            )}

            <TabsContent value="overview">
                <InspectorOverview run={run} events={events} />
            </TabsContent>

            <TabsContent value="issues">
                <RunIssues run={run} />
            </TabsContent>

            <TabsContent value="artifacts">
                <RunArtifacts run={run} />
            </TabsContent>

            <TabsContent value="parameters">
                <RunParameters run={run} />
            </TabsContent>

            {variant === 'page' && run.advanced !== null && (
                <TabsContent value="advanced">
                    <RunAdvanced run={run} />
                </TabsContent>
            )}
        </Tabs>
    );
}
