import type { DataProcessingJob, JobStatus } from '@/types';

export function formatNumber(value: number | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return new Intl.NumberFormat().format(value);
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
    const formatter = new Intl.RelativeTimeFormat(undefined, {
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

    return new Date(value).toLocaleDateString(undefined, {
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
export function resultSummary(job: DataProcessingJob): string {
    if (job.status === 'failed') {
        return job.error_message ?? 'Failed';
    }

    if (job.status === 'cancelled') {
        return 'Cancelled';
    }

    if (job.status === 'pending') {
        return 'Waiting to start';
    }

    if (job.type === 'import') {
        return [
            `Created ${formatNumber(job.counts.created ?? job.success_count)}`,
            `Skipped ${formatNumber(job.errors.filter((e) => e.type === 'duplicate').length)}`,
            `Failed ${formatNumber(job.counts.failed ?? job.error_count)}`,
        ].join(' · ');
    }

    return `${formatNumber(job.processed_items)} rows`;
}

/**
 * A rough time remaining estimate for a running job, when determinate.
 */
export function computeEta(job: DataProcessingJob): string | null {
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
        return `~${Math.round(remaining)}s left`;
    }

    return `~${Math.round(remaining / 60)}m left`;
}

export type JobGroup = {
    label: string;
    jobs: DataProcessingJob[];
};

function dayLabel(value: string | null): string {
    if (!value) {
        return 'Earlier';
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
        return 'Just now';
    }

    if (diffDays === 1) {
        return 'Yesterday';
    }

    if (diffDays < 7) {
        return date.toLocaleDateString(undefined, { weekday: 'long' });
    }

    return date.toLocaleDateString(undefined, {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
}

export function groupJobsByDay(jobs: DataProcessingJob[]): JobGroup[] {
    const groups: JobGroup[] = [];

    for (const job of jobs) {
        const label = dayLabel(job.created_at);
        const existing = groups.find((group) => group.label === label);

        if (existing) {
            existing.jobs.push(job);
        } else {
            groups.push({ label, jobs: [job] });
        }
    }

    return groups;
}
