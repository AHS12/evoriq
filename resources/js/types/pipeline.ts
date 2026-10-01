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
