import { usePage } from '@inertiajs/react';
import { useLivePoll } from '@/hooks/use-live-poll';
import { index as activityIndex } from '@/routes/activity';
import type { PipelineStatus } from '@/types';

const EMPTY: PipelineStatus = {
    active_count: 0,
    queued_count: 0,
    processing_count: 0,
    failed_recent: 0,
    worst_status: 'success',
    runs: [],
    freshness: null,
    api_budget: null,
};

/**
 * PIPE-08 — the shared global pipeline snapshot. Every consumer (header
 * indicator, sidebar badge, command palette) subscribes to the same poll entry,
 * so one partial reload keeps them all in sync.
 *
 * Polling is only enabled while something is worth watching (active runs or a
 * recent failure), so idle pages stay silent.
 */
export function usePipelineStatus(): {
    status: PipelineStatus;
    ready: boolean;
    live: boolean;
    pause: () => void;
    resume: () => void;
} {
    const page = usePage();
    const raw = page.props.pipelineStatus as PipelineStatus | undefined;
    const status = raw ?? EMPTY;

    const active = status.active_count > 0 || status.failed_recent > 0;

    const { live, pause, resume } = useLivePoll({
        // Keyed like the activity page poll so the two share one request; the
        // reload always targets the page the user is currently on.
        url: activityIndex.url(),
        only: ['pipelineStatus', 'pipeline_revision'],
        enabled: active,
        idleInterval: 15000,
    });

    return { status, ready: raw !== undefined, live, pause, resume };
}
