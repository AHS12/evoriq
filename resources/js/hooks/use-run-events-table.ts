import { useEffect, useState } from 'react';
import { events as eventsRoute } from '@/routes/activity';
import type { JobTimelineEvent } from '@/types';

type TableMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

/**
 * PIPE-06 — loads the paginated event table for the inspector's Advanced tab.
 *
 * This is the one sanctioned JSON fetch: `activity.events` returns a paginated
 * `PipelineEventResource::collection` (no Inertia page) so the table can page
 * beyond the live timeline window without re-rendering the page.
 */
export function useRunEventsTable(
    runId: number,
    enabled: boolean,
    perPage = 25,
) {
    const [page, setPage] = useState(1);
    const [events, setEvents] = useState<JobTimelineEvent[]>([]);
    const [meta, setMeta] = useState<TableMeta | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const controller = new AbortController();
        setLoading(true);

        fetch(`${eventsRoute.url(runId)}?page=${page}&per_page=${perPage}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then((json: { data?: JobTimelineEvent[]; meta?: TableMeta }) => {
                setEvents(json.data ?? []);
                setMeta(json.meta ?? null);
            })
            .catch(() => {
                // Aborted or failed — keep the previous rows.
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [runId, page, perPage, enabled]);

    return { events, meta, page, setPage, loading };
}
