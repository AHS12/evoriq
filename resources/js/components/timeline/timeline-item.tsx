import { Children, useId, useState, type ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';
import {
    MARKER_DOT,
    MARKER_RING,
    MARKER_TEXT,
    TimelineItemContext,
    type TimelineTone,
    useTimelineDensity,
    useTimelineItem,
} from '@/components/timeline/timeline-utils';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    formatDateTime,
    formatDuration,
    formatRelativeTime,
} from '@/lib/format';
import { cn } from '@/lib/utils';

type MarkerProps = {
    tone?: TimelineTone;
    /** Pulsing ring for a running entry (static under reduced motion). */
    running?: boolean;
    children?: ReactNode;
    className?: string;
};

/**
 * The toned marker on the rail. Decorative (`aria-hidden`) — the item's text
 * carries the meaning, so the timeline is never colour-only.
 */
export function TimelineMarker({
    tone = 'neutral',
    running = false,
    children,
    className,
}: MarkerProps) {
    return (
        <span
            aria-hidden
            className={cn(
                'relative z-10 flex items-center justify-center rounded-full border-2 bg-background',
                MARKER_RING[tone],
                MARKER_TEXT[tone],
                className,
            )}
        >
            {children ?? (
                <span className={cn('size-2 rounded-full', MARKER_DOT[tone])} />
            )}
            {running && (
                <span
                    className={cn(
                        'absolute -inset-1 rounded-full border-2 opacity-60 motion-safe:animate-ping',
                        MARKER_RING[tone],
                    )}
                />
            )}
        </span>
    );
}

type TimeProps = {
    value: string | number | Date | null | undefined;
    className?: string;
};

/**
 * The time gutter: a relative time in a `<time>` element, with the absolute
 * timestamp revealed on hover/focus via a tooltip.
 */
export function TimelineTime({ value, className }: TimeProps) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const date = value instanceof Date ? value : new Date(value);
    const iso = Number.isNaN(date.getTime()) ? undefined : date.toISOString();

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'shrink-0 rounded pt-0.5 text-right text-xs whitespace-nowrap text-muted-foreground outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        className,
                    )}
                >
                    <time dateTime={iso}>{formatRelativeTime(value)}</time>
                </button>
            </TooltipTrigger>
            <TooltipContent side="left">
                {formatDateTime(value, { seconds: true })}
            </TooltipContent>
        </Tooltip>
    );
}

type ContentProps = {
    title: ReactNode;
    description?: ReactNode;
    className?: string;
    children?: ReactNode;
};

/**
 * The item's text block. When the parent `<TimelineItem expandable>` has
 * detail, the heading becomes a button that toggles the detail region with
 * correct `aria-expanded` / `aria-controls`.
 */
export function TimelineContent({
    title,
    description,
    className,
    children,
}: ContentProps) {
    const item = useTimelineItem();
    const hasDetail = Children.count(children) > 0;
    const collapsible = Boolean(item?.expandable && hasDetail);

    const heading = (
        <span className="block min-w-0">
            <span className="block text-sm font-medium">{title}</span>
            {description && (
                <span className="mt-0.5 block text-xs text-muted-foreground">
                    {description}
                </span>
            )}
        </span>
    );

    const detail = hasDetail ? (
        collapsible ? (
            <div
                id={item?.contentId}
                inert={!item?.expanded}
                className={cn(
                    'grid transition-all duration-200 ease-standard',
                    item?.expanded
                        ? 'grid-rows-[1fr] opacity-100'
                        : 'grid-rows-[0fr] opacity-0',
                )}
            >
                <div className="overflow-hidden">
                    <div className="pt-2 text-xs text-muted-foreground">
                        {children}
                    </div>
                </div>
            </div>
        ) : (
            <div className="pt-1 text-xs text-muted-foreground">{children}</div>
        )
    ) : null;

    if (!collapsible) {
        return (
            <div className={cn('min-w-0', className)}>
                {heading}
                {detail}
            </div>
        );
    }

    return (
        <div className={cn('min-w-0', className)}>
            <button
                type="button"
                aria-expanded={item?.expanded}
                aria-controls={item?.contentId}
                onClick={item?.toggle}
                className="flex w-full items-start gap-2 rounded-md text-left outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            >
                <span className="min-w-0 flex-1">{heading}</span>
                <ChevronDown
                    aria-hidden
                    className={cn(
                        'mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform duration-200 ease-standard',
                        item?.expanded && 'rotate-180',
                    )}
                />
            </button>
            {detail}
        </div>
    );
}

type ItemProps = {
    tone?: TimelineTone;
    /** Custom marker glyph (e.g. an icon). Defaults to a toned dot. */
    marker?: ReactNode;
    /** Timestamp shown in the gutter; omit for un-timed entries. */
    time?: string | number | Date | null;
    /** Right-aligned duration chip (milliseconds). */
    durationMs?: number | null;
    running?: boolean;
    expandable?: boolean;
    defaultOpen?: boolean;
    className?: string;
    children?: ReactNode;
};

/**
 * One entry on the rail: time gutter, marker and content. The connector is
 * drawn by the marker column and clipped on the last item so the rail never
 * dangles.
 */
export function TimelineItem({
    tone = 'neutral',
    marker,
    time,
    durationMs,
    running = false,
    expandable = false,
    defaultOpen = false,
    className,
    children,
}: ItemProps) {
    const density = useTimelineDensity();
    const [expanded, setExpanded] = useState(defaultOpen);
    const id = useId();
    const compact = density === 'compact';

    return (
        <TimelineItemContext.Provider
            value={{
                expandable,
                expanded,
                toggle: () => setExpanded((value) => !value),
                contentId: expandable ? `${id}-detail` : undefined,
                density,
            }}
        >
            <li
                data-slot="timeline-item"
                data-tone={tone}
                data-running={running || undefined}
                className={cn('group/item relative flex gap-3', className)}
            >
                {time !== null && time !== undefined && (
                    <TimelineTime value={time} />
                )}

                <div
                    className={cn(
                        'relative flex shrink-0 justify-center',
                        compact ? 'w-5' : 'w-6',
                    )}
                >
                    <span
                        aria-hidden
                        className={cn(
                            'absolute bottom-0 w-px group-last/item:hidden',
                            running
                                ? 'bg-primary/40 motion-safe:animate-pulse'
                                : 'bg-border',
                            compact ? 'top-6' : 'top-7',
                        )}
                    />
                    <TimelineMarker
                        tone={tone}
                        running={running}
                        className={cn('mt-0.5', compact ? 'size-5' : 'size-6')}
                    >
                        {marker}
                    </TimelineMarker>
                </div>

                <div className="flex min-w-0 flex-1 items-start justify-between gap-2">
                    <div
                        className={cn(
                            'min-w-0 flex-1',
                            compact ? 'pb-3' : 'pb-5',
                        )}
                    >
                        {children}
                    </div>

                    {durationMs !== null && durationMs !== undefined && (
                        <span className="mt-0.5 shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground tabular-nums">
                            {formatDuration(durationMs / 1000)}
                        </span>
                    )}
                </div>
            </li>
        </TimelineItemContext.Provider>
    );
}
