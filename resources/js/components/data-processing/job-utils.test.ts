import { describe, expect, it } from 'vitest';
import { computeEta } from '@/components/data-processing/job-utils';
import type { DataProcessingJob } from '@/types';

const t = (
    key: string,
    replacements?: Record<string, string | number>,
): string => key.replace(':count', String(replacements?.count ?? ''));

function makeJob(
    overrides: Partial<DataProcessingJob> = {},
): DataProcessingJob {
    return {
        id: 1,
        job_id: 'job-1',
        name: 'Users export',
        run_type: 'data_processing',
        type: 'export',
        type_label: 'Export',
        type_icon: 'download',
        status: 'processing',
        status_label: 'Processing',
        entity: { value: 'users', label: 'Users', icon: 'users' },
        entity_type: 'users',
        entity_label: 'Users',
        entity_icon: 'users',
        format: 'csv',
        format_label: 'CSV',
        parameters: null,
        stage: 'generate',
        progress: {
            total: 100,
            processed: 25,
            percentage: 25,
            indeterminate: false,
            elapsed_seconds: 50,
            eta_seconds: null,
            throughput_per_min: 30,
        },
        counts: { created: 0, updated: 0, failed: 0, skipped: 0 },
        stages: [],
        attempts: { current: 1, label: 'Attempt 1', next_retry_at: null },
        failure: null,
        timing: {
            dispatched_at: null,
            started_at: null,
            completed_at: null,
            duration_ms: null,
            last_heartbeat_at: null,
            stale: false,
        },
        abilities: {
            cancel: true,
            retry: false,
            resume: false,
            duplicate: true,
            download: false,
            delete: true,
        },
        timeline: [],
        issues: [],
        advanced: null,
        file_name: null,
        file_size: null,
        input_file_name: null,
        error_message: null,
        errors: [],
        artifacts: [],
        download_url: null,
        downloadable: false,
        owner: null,
        duration: null,
        can: {
            cancel: true,
            retry: false,
            duplicate: true,
            download: false,
            delete: true,
        },
        started_at: null,
        completed_at: null,
        created_at: null,
        progress_percentage: 25,
        total_items: 100,
        processed_items: 25,
        success_count: 0,
        error_count: 0,
        ...overrides,
    };
}

describe('computeEta', () => {
    it('prefers the server-computed eta', () => {
        const job = makeJob({
            progress: { ...makeJob().progress, eta_seconds: 120 },
        });

        expect(computeEta(job, t)).toBe('~2m left');
    });

    it('formats a sub-minute eta in seconds', () => {
        const job = makeJob({
            progress: { ...makeJob().progress, eta_seconds: 45 },
        });

        expect(computeEta(job, t)).toBe('~45s left');
    });

    it('falls back to a local estimate when the server has no eta', () => {
        const job = makeJob({
            started_at: new Date(Date.now() - 50_000).toISOString(),
        });

        expect(computeEta(job, t)).toBe('~3m left');
    });

    it('returns null for a finished job', () => {
        expect(computeEta(makeJob({ status: 'completed' }), t)).toBeNull();
    });
});
