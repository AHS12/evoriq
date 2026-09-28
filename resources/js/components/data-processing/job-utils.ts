import {
    formatBytes,
    formatNumber,
    formatRelativeTime as relativeTime,
} from '@/lib/format';
import { appLocale } from '@/lib/locale';
import type { DataProcessingJob, JobStatus } from '@/types';

export type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

export { formatBytes, formatNumber, relativeTime };

export function isActiveStatus(status: JobStatus): boolean {
    return status === 'pending' || status === 'processing';
}

/**
 * A one-line summary of a finished (or pending) job.
 */
export function resultSummary(job: DataProcessingJob, t: Translate): string {
    if (job.status === 'failed') {
        return job.error_message ?? t('Failed');
    }

    if (job.status === 'cancelled') {
        return t('Cancelled');
    }

    if (job.status === 'pending') {
        return t('Waiting to start');
    }

    if (job.type === 'import') {
        return [
            t('Created :count', {
                count: formatNumber(job.counts.created ?? job.success_count),
            }),
            t('Skipped :count', {
                count: formatNumber(
                    job.errors.filter((e) => e.type === 'duplicate').length,
                ),
            }),
            t('Failed :count', {
                count: formatNumber(job.counts.failed ?? job.error_count),
            }),
        ].join(' · ');
    }

    return t(':count rows', { count: formatNumber(job.processed_items) });
}

/**
 * A time remaining estimate for a running job. Prefers the server-computed
 * `eta_seconds` (PIPE-02) and falls back to a local estimate when it is absent.
 */
export function computeEta(
    job: DataProcessingJob,
    t: Translate,
): string | null {
    if (job.status !== 'processing') {
        return null;
    }

    const remaining = job.progress.eta_seconds ?? estimateEtaSeconds(job);

    if (remaining === null) {
        return null;
    }

    if (remaining < 60) {
        return t('~:count left', { count: `${Math.round(remaining)}s` });
    }

    return t('~:count left', { count: `${Math.round(remaining / 60)}m` });
}

/**
 * Local fallback: seconds remaining extrapolated from the average rate so far.
 */
function estimateEtaSeconds(job: DataProcessingJob): number | null {
    const { processed, total } = job.progress;

    if (!processed || !total || processed <= 0 || !job.started_at) {
        return null;
    }

    const elapsed = (Date.now() - new Date(job.started_at).getTime()) / 1000;

    if (elapsed <= 0) {
        return null;
    }

    return Math.max(0, (elapsed / processed) * (total - processed));
}

export type JobGroup = {
    label: string;
    jobs: DataProcessingJob[];
};

function dayLabel(value: string | null, t: Translate): string {
    if (!value) {
        return t('Earlier');
    }

    const date = new Date(value);
    const now = new Date();
    const startOfDay = (input: Date): number =>
        new Date(
            input.getFullYear(),
            input.getMonth(),
            input.getDate(),
        ).getTime();
    const diffDays = Math.round(
        (startOfDay(now) - startOfDay(date)) / 86_400_000,
    );

    if (diffDays <= 0) {
        return t('Just now');
    }

    if (diffDays === 1) {
        return t('Yesterday');
    }

    if (diffDays < 7) {
        return date.toLocaleDateString(appLocale(), { weekday: 'long' });
    }

    return date.toLocaleDateString(appLocale(), {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export function groupJobsByDay(
    jobs: DataProcessingJob[],
    t: Translate,
): JobGroup[] {
    const groups: JobGroup[] = [];

    for (const job of jobs) {
        const label = dayLabel(job.created_at, t);
        const existing = groups.find((group) => group.label === label);

        if (existing) {
            existing.jobs.push(job);
        } else {
            groups.push({ label, jobs: [job] });
        }
    }

    return groups;
}
