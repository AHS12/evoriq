import { Fragment } from 'react';
import {
    SEGMENT_STATUS_KEY,
    SEGMENT_TONE,
    type TimelineSegmentStatus,
} from '@/components/timeline/timeline-utils';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type TimelineSegment = {
    key: string;
    label: string;
    status: TimelineSegmentStatus;
    /** Relative weight; segments are proportionally sized. */
    ratio: number;
    /** Optional detail revealed for a failed segment. */
    error?: string;
};

type Props = {
    segments: TimelineSegment[];
    ariaLabel?: string;
    /** Override the built-in status labels (already translated). */
    statusLabels?: Partial<Record<TimelineSegmentStatus, string>>;
    className?: string;
};

/**
 * PIPE-04 — a horizontal proportional track (stage lanes / mini Gantt).
 *
 * Segments are sized by `ratio` and toned by status. The whole track is an
 * accessible `role="list"` whose items carry `label: status` names, so the
 * meaning is available without seeing the colours; a failed segment is
 * focusable and reveals its error in a tooltip.
 */
export function TimelineTrack({
    segments,
    ariaLabel,
    statusLabels,
    className,
}: Props) {
    const { t } = useTranslation();

    const total = segments.reduce(
        (sum, segment) => sum + Math.max(0, segment.ratio),
        0,
    );

    const statusText = (status: TimelineSegmentStatus): string =>
        statusLabels?.[status] ?? t(SEGMENT_STATUS_KEY[status]);

    return (
        <div
            role="list"
            aria-label={ariaLabel}
            data-slot="timeline-track"
            className={cn(
                'flex h-2 overflow-hidden rounded-full bg-muted',
                className,
            )}
        >
            {segments.map((segment) => {
                const share =
                    total > 0
                        ? (Math.max(0, segment.ratio) / total) * 100
                        : 100 / Math.max(1, segments.length);

                const item = (
                    <div
                        role="listitem"
                        aria-label={`${segment.label}: ${statusText(segment.status)}`}
                        tabIndex={segment.status === 'failed' ? 0 : undefined}
                        className={cn(
                            'h-full',
                            SEGMENT_TONE[segment.status],
                            segment.status === 'failed' &&
                                'outline-none focus-visible:ring-2 focus-visible:ring-ring',
                        )}
                        style={{ width: `${share}%` }}
                    />
                );

                if (segment.status !== 'failed') {
                    return <Fragment key={segment.key}>{item}</Fragment>;
                }

                return (
                    <Tooltip key={segment.key}>
                        <TooltipTrigger asChild>{item}</TooltipTrigger>
                        <TooltipContent>
                            {segment.error ??
                                `${segment.label}: ${statusText(segment.status)}`}
                        </TooltipContent>
                    </Tooltip>
                );
            })}
        </div>
    );
}
