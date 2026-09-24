import {
    Ban,
    CheckCircle2,
    Clock,
    LoaderCircle,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { JobStatus } from '@/types';

const ICONS: Record<JobStatus, LucideIcon> = {
    pending: Clock,
    processing: LoaderCircle,
    completed: CheckCircle2,
    failed: XCircle,
    cancelled: Ban,
};

const TONES: Record<JobStatus, string> = {
    pending: 'text-amber-600 dark:text-amber-400',
    processing: 'text-sky-600 dark:text-sky-400',
    completed: 'text-emerald-600 dark:text-emerald-400',
    failed: 'text-destructive',
    cancelled: 'text-muted-foreground',
};

type Props = {
    status: JobStatus;
    className?: string;
    spinning?: boolean;
};

/**
 * GitHub-Actions-style status glyph. The processing state spins.
 */
export function JobStatusIcon({ status, className, spinning = true }: Props) {
    const Icon = ICONS[status];

    return (
        <Icon
            aria-hidden
            className={cn(
                'size-4',
                TONES[status],
                status === 'processing' &&
                    spinning &&
                    'animate-spin motion-reduce:animate-none',
                className,
            )}
        />
    );
}
