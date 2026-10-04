export type PipelineHealthStatus = 'healthy' | 'degraded' | 'unhealthy';

export type PipelineFailureReasonCount = {
    reason: string;
    label: string;
    count: number;
};

export type PipelineMetrics = {
    active_now: number;
    queued: number;
    runs_today: number;
    completed_7d: number;
    failed_7d: number;
    success_rate_7d: number | null;
    avg_duration_ms: number | null;
    p95_duration_ms: number | null;
    records_7d: number;
    failures_24h: number;
    stale: number;
    failure_reasons: PipelineFailureReasonCount[];
};

export type QueueDepthChannel = {
    channel: string;
    depth: number;
};

export type QueueDepth = {
    available: boolean;
    driver: string;
    channels: QueueDepthChannel[];
};

export type PipelineRecentRun = {
    id: number;
    job_id: string;
    name: string | null;
    type: string;
    type_label: string;
    entity: string | null;
    entity_label: string | null;
    status: string;
    status_label: string;
    duration_ms: number | null;
    records: number;
    started_at: string | null;
    created_at: string | null;
    action_url: string;
};

export type PipelineRecentFailure = {
    id: number;
    job_id: string;
    type_label: string;
    entity_label: string | null;
    reason: string | null;
    reason_label: string | null;
    hint: string | null;
    action: string | null;
    error_message: string | null;
    failed_at: string | null;
    action_url: string;
    retry_url: string;
};
