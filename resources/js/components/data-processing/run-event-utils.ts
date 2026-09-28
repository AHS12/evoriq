import {
    Ban,
    Check,
    CircleAlert,
    CircleCheck,
    CirclePlay,
    FileCheck,
    Info,
    Loader,
    Play,
    RotateCw,
    Send,
    TimerReset,
    TriangleAlert,
    X,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Translate } from '@/components/data-processing/job-utils';
import type { TimelineTone } from '@/components/timeline/timeline-utils';
import { formatNumber } from '@/lib/format';
import type { JobTimelineEvent, RunEventFilter } from '@/types';

/**
 * PIPE-05 — presentation helpers for a run's event stream. Pure functions so
 * they can be unit-tested and reused by the run detail inspector (PIPE-06).
 */

const TYPE_TONES: Record<string, TimelineTone> = {
    dispatched: 'neutral',
    started: 'info',
    stage_started: 'info',
    progress: 'muted',
    stage_completed: 'success',
    info: 'neutral',
    warning: 'warning',
    error: 'error',
    retry_scheduled: 'warning',
    retry_started: 'warning',
    artifact_ready: 'success',
    completed: 'success',
    cancelled: 'muted',
    failed: 'error',
};

const LEVEL_TONES: Record<string, TimelineTone> = {
    debug: 'muted',
    info: 'info',
    success: 'success',
    warning: 'warning',
    error: 'error',
};

const TYPE_ICONS: Record<string, LucideIcon> = {
    dispatched: Send,
    started: Play,
    stage_started: CirclePlay,
    progress: Loader,
    stage_completed: CircleCheck,
    info: Info,
    warning: TriangleAlert,
    error: CircleAlert,
    retry_scheduled: TimerReset,
    retry_started: RotateCw,
    artifact_ready: FileCheck,
    completed: Check,
    cancelled: Ban,
    failed: X,
};

/** Event types whose change is worth announcing to assistive tech. */
export const ANNOUNCEABLE_TYPES = new Set([
    'stage_started',
    'stage_completed',
    'warning',
    'error',
    'retry_scheduled',
    'completed',
    'failed',
    'cancelled',
]);

/** Keep the timeline DOM bounded on very chatty runs. */
export const TIMELINE_DOM_CAP = 500;

export function eventTone(event: JobTimelineEvent): TimelineTone {
    return TYPE_TONES[event.type] ?? LEVEL_TONES[event.level] ?? 'info';
}

export function eventIcon(event: JobTimelineEvent): LucideIcon {
    return TYPE_ICONS[event.type] ?? Info;
}

/** `write_records` → `Write Records`. */
export function headline(value: string): string {
    return value
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase())
        .trim();
}

export function eventTitle(event: JobTimelineEvent, t: Translate): string {
    const label = t(event.type_label);

    return event.stage ? `${label} · ${headline(event.stage)}` : label;
}

export function eventDescription(event: JobTimelineEvent): string | null {
    if (event.type === 'progress' && event.progress) {
        const { processed, total } = event.progress as {
            processed?: unknown;
            total?: unknown;
        };

        if (typeof processed === 'number' && typeof total === 'number') {
            return `${formatNumber(processed)} / ${formatNumber(total)}`;
        }
    }

    return event.message ?? null;
}

export function isIssue(event: JobTimelineEvent): boolean {
    return (
        event.level === 'warning' ||
        event.level === 'error' ||
        event.type === 'failed'
    );
}

export function isStageEvent(event: JobTimelineEvent): boolean {
    return event.type === 'stage_started' || event.type === 'stage_completed';
}

/**
 * Whether an event carries renderable context worth an expandable detail.
 * The recorder defaults context to `[]`, so empty arrays/objects (and `{}` /`[]`
 * string previews) count as "no context" and render no detail block.
 */
export function hasContext(event: JobTimelineEvent): boolean {
    const context = event.context;

    if (context === null || context === undefined) {
        return false;
    }

    if (typeof context === 'string') {
        const trimmed = context.trim();

        return trimmed !== '' && trimmed !== '{}' && trimmed !== '[]';
    }

    if (Array.isArray(context)) {
        return context.length > 0;
    }

    return Object.keys(context).length > 0;
}

/**
 * Collapse consecutive PROGRESS events into a single rolling row so the stream
 * stays readable without losing the latest signal.
 */
export function collapseProgress(
    events: JobTimelineEvent[],
): JobTimelineEvent[] {
    const result: JobTimelineEvent[] = [];

    for (let index = 0; index < events.length; index += 1) {
        const current = events[index];
        const next = events[index + 1];

        if (current.type === 'progress' && next?.type === 'progress') {
            continue;
        }

        result.push(current);
    }

    return result;
}

export function filterEvents(
    events: JobTimelineEvent[],
    filter: RunEventFilter,
    search: string,
): JobTimelineEvent[] {
    const term = search.trim().toLowerCase();

    return events.filter((event) => {
        if (filter === 'stages' && !isStageEvent(event)) {
            return false;
        }

        if (filter === 'issues' && !isIssue(event)) {
            return false;
        }

        if (term !== '') {
            const haystack =
                `${event.type_label} ${event.message ?? ''} ${event.stage ?? ''}`.toLowerCase();

            if (!haystack.includes(term)) {
                return false;
            }
        }

        return true;
    });
}

/**
 * Merge an incoming event window into the loaded set, deduped by sequence and
 * capped at {@link TIMELINE_DOM_CAP} (newest kept). Handles both appends (poll
 * tail) and prepends (older pages).
 */
export function mergeEvents(
    previous: JobTimelineEvent[],
    incoming: JobTimelineEvent[],
): JobTimelineEvent[] {
    if (incoming.length === 0) {
        return previous;
    }

    const bySequence = new Map<number, JobTimelineEvent>();

    for (const event of previous) {
        bySequence.set(event.sequence, event);
    }

    for (const event of incoming) {
        bySequence.set(event.sequence, event);
    }

    const merged = Array.from(bySequence.values()).sort(
        (a, b) => a.sequence - b.sequence,
    );

    return merged.length > TIMELINE_DOM_CAP
        ? merged.slice(merged.length - TIMELINE_DOM_CAP)
        : merged;
}

/** The most recent announceable event's text, or null. */
export function latestAnnouncement(events: JobTimelineEvent[]): string | null {
    for (let index = events.length - 1; index >= 0; index -= 1) {
        const event = events[index];

        if (ANNOUNCEABLE_TYPES.has(event.type)) {
            return event.message ?? event.type_label;
        }
    }

    return null;
}
