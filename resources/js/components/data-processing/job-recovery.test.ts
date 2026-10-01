import { describe, expect, it } from 'vitest';
import {
    attemptLabel,
    deleteConfirmation,
    isRetryPending,
    runActions,
    type RecoveryAction,
} from '@/components/data-processing/job-recovery';
import type { DataProcessingJob, JobAbilities } from '@/types';

const t = (
    key: string,
    replacements?: Record<string, string | number>,
): string => {
    if (replacements === undefined) {
        return key;
    }

    return Object.entries(replacements).reduce(
        (value, [name, replacement]) =>
            value.replace(`:${name}`, String(replacement)),
        key,
    );
};

const ABILITIES: JobAbilities = {
    cancel: false,
    retry: false,
    resume: false,
    duplicate: false,
    duplicate_soon: false,
    download: false,
    delete: false,
};

function makeJob(
    overrides: Partial<DataProcessingJob> = {},
): DataProcessingJob {
    return {
        id: 1,
        job_id: 'job-1',
        name: 'Users import',
        run_type: 'data_processing',
        type: 'import',
        type_label: 'Import',
        type_icon: 'upload',
        status: 'failed',
        status_label: 'Failed',
        entity: { value: 'users', label: 'Users', icon: 'users' },
        entity_type: 'users',
        entity_label: 'Users',
        entity_icon: 'users',
        format: 'csv',
        format_label: 'CSV',
        parameters: null,
        stage: null,
        progress: {
            total: null,
            processed: null,
            percentage: 0,
            indeterminate: false,
            elapsed_seconds: 0,
            eta_seconds: null,
            throughput_per_min: null,
        },
        counts: { created: 0, updated: 0, failed: 0, skipped: 0 },
        stages: [],
        attempts: {
            current: 1,
            label: 'Attempt 1',
            max: 3,
            next_retry_at: null,
        },
        failure: null,
        timing: {
            dispatched_at: null,
            started_at: null,
            completed_at: null,
            duration_ms: null,
            last_heartbeat_at: null,
            stale: false,
        },
        abilities: { ...ABILITIES },
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
        can: { ...ABILITIES },
        started_at: null,
        completed_at: null,
        created_at: null,
        progress_percentage: 0,
        total_items: null,
        processed_items: null,
        success_count: null,
        error_count: null,
        ...overrides,
    };
}

function keys(actions: RecoveryAction[]): string[] {
    return actions.map((action) => action.key);
}

describe('runActions', () => {
    it('makes Stop the primary action for a processing run and confirms it', () => {
        const { primary } = runActions(
            makeJob({
                status: 'processing',
                abilities: { ...ABILITIES, cancel: true, delete: true },
            }),
            t,
        );

        expect(primary).toMatchObject({
            key: 'cancel',
            label: 'Stop',
            confirm: true,
        });
    });

    it('does not confirm cancelling a queued run', () => {
        const { primary } = runActions(
            makeJob({
                status: 'pending',
                abilities: { ...ABILITIES, cancel: true },
            }),
            t,
        );

        expect(primary?.confirm).toBe(false);
    });

    it('maps a retryable failure to Retry', () => {
        const { primary } = runActions(
            makeJob({
                abilities: { ...ABILITIES, retry: true },
                failure: {
                    reason: 'rate_limited',
                    label: 'Rate limited',
                    hint: 'Wait',
                    action: 'retry',
                    message: null,
                },
            }),
            t,
        );

        expect(primary?.key).toBe('retry');
    });

    it('maps an auth failure to a disabled reconnect action', () => {
        const { primary } = runActions(
            makeJob({
                abilities: { ...ABILITIES, retry: true },
                failure: {
                    reason: 'auth_failed',
                    label: 'Authentication failed',
                    hint: 'Reconnect',
                    action: 'reconnect',
                    message: null,
                },
            }),
            t,
        );

        expect(primary).toMatchObject({
            key: 'reconnect',
            disabled: true,
        });
    });

    it('offers resume as a secondary action when the strategy exists', () => {
        const { primary, secondary } = runActions(
            makeJob({
                abilities: { ...ABILITIES, retry: true, resume: true },
            }),
            t,
        );

        expect(primary?.key).toBe('retry');
        expect(keys(secondary)).toContain('resume');
        expect(keys(secondary)).not.toContain('retry');
    });

    it('offers download as the primary action for a completed run', () => {
        const { primary } = runActions(
            makeJob({
                status: 'completed',
                download_url: '/exports/1',
                abilities: { ...ABILITIES, download: true, duplicate: true },
            }),
            t,
        );

        expect(primary?.key).toBe('download');
    });
});

describe('isRetryPending', () => {
    it('is true only while the scheduled retry is in the future', () => {
        const future = makeJob({
            attempts: {
                current: 1,
                label: 'Attempt 1',
                max: 3,
                next_retry_at: new Date(Date.now() + 60_000).toISOString(),
            },
        });
        const past = makeJob({
            attempts: {
                current: 1,
                label: 'Attempt 1',
                max: 3,
                next_retry_at: new Date(Date.now() - 60_000).toISOString(),
            },
        });

        expect(isRetryPending(future)).toBe(true);
        expect(isRetryPending(past)).toBe(false);
        expect(isRetryPending(makeJob())).toBe(false);
    });
});

describe('attemptLabel', () => {
    it('includes the max when the queue exposes more than one attempt', () => {
        const job = makeJob({
            attempts: {
                current: 2,
                label: 'Attempt 2',
                max: 3,
                next_retry_at: null,
            },
        });

        expect(attemptLabel(job, t)).toBe('Attempt 2/3');
    });
});

describe('deleteConfirmation', () => {
    it('warns about interrupting an active run', () => {
        const copy = deleteConfirmation(makeJob({ status: 'processing' }), t);

        expect(copy.title).toBe('Delete while running?');
    });

    it('states the permanent consequence for a finished run', () => {
        const copy = deleteConfirmation(makeJob({ status: 'failed' }), t);

        expect(copy.title).toBe('Delete job?');
    });
});
