import { Children, type ReactNode } from 'react';
import { cn } from '@/lib/utils';
import {
    TimelineDensityContext,
    type TimelineDensity,
} from '@/components/timeline/timeline-utils';

type TimelineProps = {
    /** Vertical rhythm; `compact` suits cards and sheets. */
    density?: TimelineDensity;
    /** Render in place of the (empty) list; nothing is rendered without it. */
    emptyState?: ReactNode;
    className?: string;
    children?: ReactNode;
    'aria-label'?: string;
};

/**
 * PIPE-04 — a vertical event timeline.
 *
 * Renders an ordered list (`<ol>`); each `<TimelineItem>` is an `<li>` and a
 * `<TimelineGroup>` is an `<li>` wrapping its own `<ol>`. When there are no
 * children the primitive renders `emptyState` (or nothing) so hosts can supply
 * the shared `EmptyState`.
 */
export function Timeline({
    density = 'comfortable',
    emptyState,
    className,
    children,
    ...props
}: TimelineProps) {
    if (Children.count(children) === 0) {
        return <>{emptyState ?? null}</>;
    }

    return (
        <TimelineDensityContext.Provider value={density}>
            <ol
                data-slot="timeline"
                data-density={density}
                className={cn('relative', className)}
                {...props}
            >
                {children}
            </ol>
        </TimelineDensityContext.Provider>
    );
}

type TimelineGroupProps = {
    /** Group heading (e.g. `t('Today')`). */
    label?: ReactNode;
    className?: string;
    children?: ReactNode;
};

/**
 * A labelled day/section grouping. The label sticks to the top of the scroll
 * container so long timelines stay readable.
 */
export function TimelineGroup({
    label,
    className,
    children,
}: TimelineGroupProps) {
    return (
        <li data-slot="timeline-group" className={cn('relative', className)}>
            {label && (
                <div className="sticky top-0 z-20 -mx-1 mb-2 bg-background/90 px-1 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase backdrop-blur">
                    {label}
                </div>
            )}
            <ol className="relative">{children}</ol>
        </li>
    );
}
