import type { JobStatus } from '@/types/data-processing';

/**
 * PIPE-08 — the shared, cheap global pipeline snapshot rendered by the header
 * indicator and sidebar badge.
 */

export type PipelineTone = 'error' | 'warning' | 'info' | 'success';

export type PipelineStatusRun = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    status: JobStatus;
    status_label: string;
    entity_icon: string | null;
    stage: string | null;
    percentage: number;
    indeterminate: boolean;
    eta_seconds: number | null;
};

export type PipelineFreshness = {
    last_synced_at: string | null;
    data_through: string | null;
    next_sync_at: string | null;
};

export type PipelineApiBudget = {
    used: number;
    remaining: number;
    limit: number;
    resets_at: string | null;
};

/**
 * SYNC-17 — the active connection's current API budget window for the navbar
 * indicator/popover. `window_type` is `hour` on Free plans and `second` on paid.
 */
export type ApiUsage = {
    window_type: 'hour' | 'second';
    used: number;
    limit: number;
    remaining: number;
    resets_at: string;
    resets_in: number;
    last_request_at: string | null;
    plan: 'free' | 'paid';
    low: boolean;
    exhausted: boolean;
};

export type PipelineStatus = {
    active_count: number;
    queued_count: number;
    processing_count: number;
    failed_recent: number;
    worst_status: PipelineTone;
    runs: PipelineStatusRun[];
    /** Reserved slot — populated once CONN/SYNC land. */
    freshness: PipelineFreshness | null;
    /** Reserved slot — populated once SYNC-02 lands. */
    api_budget: PipelineApiBudget | null;
};
