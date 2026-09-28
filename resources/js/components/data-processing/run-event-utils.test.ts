import { describe, expect, it } from 'vitest';
import {
    collapseProgress,
    eventTitle,
    eventTone,
    filterEvents,
    hasContext,
    latestAnnouncement,
    mergeEvents,
    TIMELINE_DOM_CAP,
} from '@/components/data-processing/run-event-utils';
import type { JobTimelineEvent } from '@/types';

let counter = 0;

function makeEvent(
    overrides: Partial<JobTimelineEvent> = {},
): JobTimelineEvent {
    counter += 1;

    return {
        id: counter,
        sequence: counter,
        type: 'info',
        type_label: 'Info',
        level: 'info',
        stage: null,
        message: null,
        context: null,
        context_truncated: false,
        progress: null,
        attempt: 1,
        duration_ms: null,
        occurred_at: '2026-09-28T01:00:00Z',
        ...overrides,
    };
}

const t = (key: string): string => key;

describe('collapseProgress', () => {
    it('keeps only the last of consecutive progress events', () => {
        const events = [
            makeEvent({ sequence: 1, type: 'progress' }),
            makeEvent({ sequence: 2, type: 'progress' }),
            makeEvent({ sequence: 3, type: 'progress' }),
            makeEvent({ sequence: 4, type: 'info' }),
            makeEvent({ sequence: 5, type: 'progress' }),
        ];

        expect(collapseProgress(events).map((event) => event.sequence)).toEqual(
            [3, 4, 5],
        );
    });
});

describe('filterEvents', () => {
    const events = [
        makeEvent({ sequence: 1, type: 'stage_started', level: 'info' }),
        makeEvent({
            sequence: 2,
            type: 'warning',
            level: 'warning',
            message: 'duplicate email',
        }),
        makeEvent({ sequence: 3, type: 'info', level: 'info', stage: 'write' }),
    ];

    it('filters to stage events', () => {
        expect(filterEvents(events, 'stages', '')).toHaveLength(1);
    });

    it('filters to issues', () => {
        expect(filterEvents(events, 'issues', '')).toHaveLength(1);
    });

    it('searches message and stage', () => {
        expect(filterEvents(events, 'all', 'duplicate')).toHaveLength(1);
        expect(filterEvents(events, 'all', 'write')).toHaveLength(1);
    });
});

describe('hasContext', () => {
    it('treats null and empty payloads as no context', () => {
        expect(hasContext(makeEvent({ context: null }))).toBe(false);
        expect(hasContext(makeEvent({ context: [] }))).toBe(false);
        expect(hasContext(makeEvent({ context: {} }))).toBe(false);
        expect(hasContext(makeEvent({ context: '[]' }))).toBe(false);
    });

    it('detects meaningful context', () => {
        expect(hasContext(makeEvent({ context: { rows: 5 } }))).toBe(true);
        expect(hasContext(makeEvent({ context: ['x'] }))).toBe(true);
        expect(hasContext(makeEvent({ context: '{"a":1}' }))).toBe(true);
    });
});

describe('mergeEvents', () => {
    it('dedupes by sequence and sorts ascending', () => {
        const merged = mergeEvents(
            [makeEvent({ sequence: 2 }), makeEvent({ sequence: 1 })],
            [
                makeEvent({ sequence: 2, message: 'updated' }),
                makeEvent({ sequence: 3 }),
            ],
        );

        expect(merged.map((event) => event.sequence)).toEqual([1, 2, 3]);
        expect(merged[1].message).toBe('updated');
    });

    it('caps the DOM window to the newest events', () => {
        const many = Array.from({ length: TIMELINE_DOM_CAP + 25 }, (_, index) =>
            makeEvent({ sequence: index + 1 }),
        );

        const merged = mergeEvents([], many);

        expect(merged).toHaveLength(TIMELINE_DOM_CAP);
        expect(merged[0].sequence).toBe(26);
    });
});

describe('presentation helpers', () => {
    it('maps event type to a tone', () => {
        expect(eventTone(makeEvent({ type: 'failed' }))).toBe('error');
        expect(eventTone(makeEvent({ type: 'retry_scheduled' }))).toBe(
            'warning',
        );
        expect(eventTone(makeEvent({ type: 'stage_completed' }))).toBe(
            'success',
        );
    });

    it('combines the type label and stage', () => {
        expect(
            eventTitle(
                makeEvent({ type_label: 'Stage started', stage: 'write_rows' }),
                t,
            ),
        ).toBe('Stage started · Write Rows');
    });

    it('announces the latest meaningful event only', () => {
        const events = [
            makeEvent({ sequence: 1, type: 'progress' }),
            makeEvent({
                sequence: 2,
                type: 'stage_completed',
                message: 'done',
            }),
            makeEvent({ sequence: 3, type: 'progress' }),
        ];

        expect(latestAnnouncement(events)).toBe('done');
    });
});
