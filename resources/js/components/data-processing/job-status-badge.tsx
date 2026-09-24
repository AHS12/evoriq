import { JobStatusIcon } from '@/components/data-processing/job-status-icon';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { JobStatus } from '@/types';

const TONES: Record<JobStatus, string> = {
    pending:
        'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    processing:
        'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    completed:
        'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    failed: 'border-destructive/30 bg-destructive/10 text-destructive',
    cancelled: 'border-border bg-muted text-muted-foreground',
};

type Props = {
    status: JobStatus;
    label: string;
};

export function JobStatusBadge({ status, label }: Props) {
    return (
        <Badge variant="outline" className={cn('gap-1.5', TONES[status])}>
            <JobStatusIcon status={status} className="size-3.5" />
            {label}
        </Badge>
    );
}
