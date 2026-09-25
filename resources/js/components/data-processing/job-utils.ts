import type { DataProcessingJob, JobStatus } from '@/types';
import { appLocale } from '@/lib/locale';

export type Translate = (
    key: string,
    replacements?: Record<string, string | number>,
) => string;

export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat(appLocale()).format(value);
}

export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}

export function relativeTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const diff = new Date(value).getTime() - Date.now();
    const abs = Math.abs(diff);
    const formatter = new Intl.RelativeTimeFormat(appLocale(), {
        numeric: 'auto',
    });

    if (abs < 60_000) {
        return formatter.format(Math.round(diff / 1000), 'second');
    }

    if (abs < 3_600_000) {
        return formatter.format(Math.round(diff / 60_000), 'minute');
    }

    if (abs < 86_400_000) {
        return formatter.format(Math.round(diff / 3_600_000), 'hour');
    }

    if (abs < 604_800_000) {
        return formatter.format(Math.round(diff / 86_400_000), 'day');
    }

    return new Date(value).toLocaleDateString(appLocale(), {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

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
 * A rough time remaining estimate for a running job, when determinate.
 */
export function computeEta(
    job: DataProcessingJob,
    t: Translate,
): string | null {
    if (job.status !== 'processing') {
        return null;
    }

    const { processed, total } = job.progress;

    if (!processed || !total || processed <= 0 || !job.started_at) {
        return null;
    }

    const elapsed = (Date.now() - new Date(job.started_at).getTime()) / 1000;

    if (elapsed <= 0) {
        return null;
    }

    const remaining = Math.max(0, (elapsed / processed) * (total - processed));

    if (remaining < 60) {
        return t('~:count left', { count: `${Math.round(remaining)}s` });
    }

    return t('~:count left', { count: `${Math.round(remaining / 60)}m` });
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
