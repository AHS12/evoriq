import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { JobStatusIcon } from '@/components/data-processing/job-status-icon';
import {
    eventTitle,
    headline,
    isIssue,
} from '@/components/data-processing/run-event-utils';
import {
    TimelineTrack,
    type TimelineSegment,
} from '@/components/timeline/timeline-track';
import type { TimelineSegmentStatus } from '@/components/timeline/timeline-utils';
import { formatDuration, formatNumber } from '@/lib/format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type {
    DataProcessingJob,
    JobStage,
    JobStatus,
    JobTimelineEvent,
} from '@/types';

type Props = {
    run: DataProcessingJob;
    events: JobTimelineEvent[];
};

const SEGMENT_STATUS: Record<JobStage['status'], TimelineSegmentStatus> = {
    pending: 'pending',
    running: 'running',
    completed: 'completed',
    failed: 'failed',
    cancelled: 'skipped',
};

const ICON_STATUS: Record<JobStage['status'], JobStatus> = {
    pending: 'pending',
    running: 'processing',
    completed: 'completed',
    failed: 'failed',
    cancelled: 'cancelled',
};

/**
 * PIPE-05 — stage lanes: a proportional `TimelineTrack` summary over one
 * expandable row per stage (status, duration, progress, issue count).
 */
export function StageLanes({ run, events }: Props) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState<string | null>(null);

    if (run.stages.length === 0) {
        return null;
    }

    const segments: TimelineSegment[] = run.stages.map((stage) => ({
        key: stage.key,
        label: headline(stage.key),
        status: SEGMENT_STATUS[stage.status],
        ratio: Math.max(0, stage.processed ?? stage.duration_ms ?? 1),
    }));

    return (
        <section className="space-y-3">
            <h2 className="text-sm font-medium">{t('Stages')}</h2>

            <TimelineTrack segments={segments} ariaLabel={t('Stages')} />

            <ul className="divide-y overflow-hidden rounded-lg border">
                {run.stages.map((stage) => {
                    const open = expanded === stage.key;
                    const stageEvents = events.filter(
                        (event) => event.stage === stage.key,
                    );
                    const issues = stageEvents.filter(isIssue).length;

                    return (
                        <li key={stage.key} data-slot="stage-row">
                            <button
                                type="button"
                                aria-expanded={open}
                                onClick={() =>
                                    setExpanded(open ? null : stage.key)
                                }
                                className="flex w-full items-center gap-3 px-3 py-2 text-left transition-smooth-fast outline-none hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <JobStatusIcon
                                    status={ICON_STATUS[stage.status]}
                                    spinning={stage.status === 'running'}
                                />
                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                    {headline(stage.key)}
                                </span>
                                {issues > 0 && (
                                    <span className="text-xs text-warning">
                                        {t(':count issues', { count: issues })}
                                    </span>
                                )}
                                {stage.processed != null && (
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {formatNumber(stage.processed)}
                                        {stage.total != null
                                            ? ` / ${formatNumber(stage.total)}`
                                            : ''}
                                    </span>
                                )}
                                {stage.duration_ms != null && (
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {formatDuration(
                                            stage.duration_ms / 1000,
                                        )}
                                    </span>
                                )}
                                <ChevronDown
                                    aria-hidden
                                    className={cn(
                                        'size-4 shrink-0 text-muted-foreground transition-transform duration-200 ease-standard',
                                        open && 'rotate-180',
                                    )}
                                />
                            </button>

                            {open && (
                                <div className="border-t bg-muted/20 px-3 py-2">
                                    {stageEvents.length > 0 ? (
                                        <ul className="space-y-1 text-xs text-muted-foreground">
                                            {stageEvents.map((event) => (
                                                <li key={event.id}>
                                                    {eventTitle(event, t)}
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            {t('No events for this stage.')}
                                        </p>
                                    )}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
