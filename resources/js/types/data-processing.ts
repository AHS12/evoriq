export type JobStatus =
    | 'pending'
    | 'processing'
    | 'completed'
    | 'failed'
    | 'cancelled';

export type JobType = 'import' | 'export' | 'report';

export type JobError = {
    row: number;
    type: string;
    message: string;
};

export type JobProgress = {
    total: number | null;
    processed: number | null;
    percentage: number;
    indeterminate: boolean;
};

export type JobCounts = {
    created: number | null;
    failed: number | null;
};

export type JobArtifact = {
    key: string;
    label: string;
    file_name: string | null;
    size: number | null;
    mime_type: string | null;
    downloadable: boolean;
    expires_at: string | null;
};

export type JobAbilities = {
    cancel: boolean;
    retry: boolean;
    duplicate: boolean;
    download: boolean;
    delete: boolean;
};

export type DataProcessingJob = {
    id: number;
    job_id: string;
    name: string;
    type: JobType;
    type_label: string;
    type_icon: string;
    status: JobStatus;
    status_label: string;
    entity_type: string | null;
    entity_label: string | null;
    entity_icon: string | null;
    format: string | null;
    format_label: string | null;
    parameters: Record<string, unknown> | null;
    stage: string | null;
    progress: JobProgress;
    counts: JobCounts;
    file_name: string | null;
    file_size: number | null;
    input_file_name: string | null;
    error_message: string | null;
    errors: JobError[];
    artifacts: JobArtifact[];
    download_url: string | null;
    downloadable: boolean;
    owner: string | null;
    duration: string | null;
    can: JobAbilities;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
    progress_percentage: number;
    total_items: number | null;
    processed_items: number | null;
    success_count: number | null;
    error_count: number | null;
};

export type JobStats = {
    total: number;
    pending: number;
    processing: number;
    completed: number;
    failed: number;
    cancelled: number;
};

export type JobEntityOption = {
    value: string;
    label: string;
    icon: string;
    export: boolean;
    import: boolean;
    report: boolean;
    import_headings: string[];
    import_column_help: Record<string, string>;
};

export type JobFormatOption = {
    value: string;
    label: string;
};

export type JobStatusOption = {
    value: JobStatus;
    label: string;
};

export type JobTypeOption = {
    value: JobType;
    label: string;
    icon: string;
};

export type JobOptions = {
    entities: JobEntityOption[];
    formats: JobFormatOption[];
    statuses: JobStatusOption[];
    types: JobTypeOption[];
    roles: string[];
};

export type JobFilters = {
    search?: string | null;
    type?: JobType | null;
    status?: JobStatus | null;
    entity_type?: string | null;
    order_by?: string | null;
    order_direction?: string | null;
    per_page?: number | string | null;
    page?: number | string | null;
};

export type QueuedJob = {
    name: string;
    job_id: string;
};
