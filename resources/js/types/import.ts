import type { Connection, RegionOption } from '@/types/connection';
import type { DataProcessingJob } from '@/types/data-processing';

/**
 * PIPE-09 / ENT-14 — the historical import wizard contract. The live run uses
 * the shared pipeline run shape (`DataProcessingJob`), so PIPE-05 components
 * render sync runs unchanged.
 */

export type ImportWorkspace = {
    clockify_id: string;
    name: string;
    currency: string | null;
    time_zone: string | null;
};

export type ImportPlanJob = {
    entity_type: string;
    entity_label: string;
    phase: string;
    priority: string;
    range_start: string | null;
    range_end: string | null;
    user_id: string | null;
    page_size: number;
    estimated_requests: number;
};

export type ImportPlanPhase = {
    phase: string;
    phase_label: string;
    job_count: number;
    estimated_requests: number;
    jobs: ImportPlanJob[];
};

export type ImportPlanEstimate = {
    jobs: number;
    partitions: number;
    requests: number;
    window_type: string;
    requests_per_window: number;
    window_seconds: number;
    estimated_seconds: number;
};

export type ImportPlan = {
    mode: string;
    priority: string;
    range_start: string;
    range_end: string;
    page_size: number;
    total_jobs: number;
    phases: ImportPlanPhase[];
    estimate: ImportPlanEstimate;
};

export type ImportWizardProps = {
    connection: Connection | null;
    workspace: ImportWorkspace | null;
    activeImport: DataProcessingJob | null;
    maxHistoryYears: number;
    regions: RegionOption[];
};

export type ImportRangePreset = {
    key: string;
    label: string;
    description: string;
    years: number | null;
};
