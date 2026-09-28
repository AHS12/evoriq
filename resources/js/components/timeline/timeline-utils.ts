import { createContext, useContext } from 'react';

/**
 * PIPE-04 — shared timeline vocabulary.
 *
 * Tones map to the semantic tokens so the timeline reads the same in every
 * theme (light/dark, dracula, khaki, high contrast). Keep raw Tailwind palette
 * colours out of the components.
 */

export type TimelineTone =
    | 'neutral'
    | 'info'
    | 'success'
    | 'warning'
    | 'error'
    | 'muted';

export type TimelineDensity = 'compact' | 'comfortable';

export type TimelineSegmentStatus =
    | 'pending'
    | 'running'
    | 'completed'
    | 'failed'
    | 'skipped';

/** Marker ring/border per tone. */
export const MARKER_RING: Record<TimelineTone, string> = {
    neutral: 'border-muted-foreground/40',
    info: 'border-info/50',
    success: 'border-success/50',
    warning: 'border-warning/50',
    error: 'border-destructive/50',
    muted: 'border-border',
};

/** Marker dot fill per tone (used when no custom icon is supplied). */
export const MARKER_DOT: Record<TimelineTone, string> = {
    neutral: 'bg-muted-foreground',
    info: 'bg-info',
    success: 'bg-success',
    warning: 'bg-warning',
    error: 'bg-destructive',
    muted: 'bg-muted-foreground/40',
};

/** Marker icon colour per tone. */
export const MARKER_TEXT: Record<TimelineTone, string> = {
    neutral: 'text-muted-foreground',
    info: 'text-info',
    success: 'text-success',
    warning: 'text-warning',
    error: 'text-destructive',
    muted: 'text-muted-foreground',
};

/** Horizontal track segment fill per status. */
export const SEGMENT_TONE: Record<TimelineSegmentStatus, string> = {
    pending: 'bg-muted',
    running: 'bg-info motion-safe:animate-pulse',
    completed: 'bg-success',
    failed: 'bg-destructive',
    skipped: 'bg-muted-foreground/40',
};

/**
 * English source keys for a segment's status; translate with `t(...)` in the
 * consuming component (or rely on the track's own `useTranslation`).
 */
export const SEGMENT_STATUS_KEY: Record<TimelineSegmentStatus, string> = {
    pending: 'Pending',
    running: 'Running',
    completed: 'Completed',
    failed: 'Failed',
    skipped: 'Skipped',
};

type TimelineItemContextValue = {
    expandable: boolean;
    expanded: boolean;
    toggle: () => void;
    contentId?: string;
    density: TimelineDensity;
};

export const TimelineItemContext =
    createContext<TimelineItemContextValue | null>(null);

export const TimelineDensityContext =
    createContext<TimelineDensity>('comfortable');

export function useTimelineDensity(): TimelineDensity {
    return useContext(TimelineDensityContext);
}

export function useTimelineItem(): TimelineItemContextValue | null {
    return useContext(TimelineItemContext);
}
