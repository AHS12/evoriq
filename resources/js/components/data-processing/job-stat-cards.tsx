import {
    Ban,
    CheckCircle2,
    Clock,
    Loader2,
    XCircle,
    type LucideIcon,
} from 'lucide-react';
import { formatNumber } from '@/components/data-processing/job-utils';
import { cn } from '@/lib/utils';
import type { JobStats, JobStatus } from '@/types';

type Card = {
    key: keyof JobStats;
    status: JobStatus;
    label: string;
    icon: LucideIcon;
    tone: string;
};

const CARDS: Card[] = [
    {
        key: 'pending',
        status: 'pending',
        label: 'Queued',
        icon: Clock,
        tone: 'text-amber-600 dark:text-amber-400',
    },
    {
        key: 'processing',
        status: 'processing',
        label: 'Running',
        icon: Loader2,
        tone: 'text-sky-600 dark:text-sky-400',
    },
    {
        key: 'completed',
        status: 'completed',
        label: 'Completed',
        icon: CheckCircle2,
        tone: 'text-emerald-600 dark:text-emerald-400',
    },
    {
        key: 'failed',
        status: 'failed',
        label: 'Failed',
        icon: XCircle,
        tone: 'text-destructive',
    },
    {
        key: 'cancelled',
        status: 'cancelled',
        label: 'Cancelled',
        icon: Ban,
        tone: 'text-muted-foreground',
    },
];

type Props = {
    stats: JobStats;
    selected: JobStatus | 'all';
    onSelect: (status: JobStatus | 'all') => void;
};

export function JobStatCards({ stats, selected, onSelect }: Props) {
    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {CARDS.map((card) => {
                const Icon = card.icon;
                const active = selected === card.status;

                return (
                    <button
                        key={card.key}
                        type="button"
                        onClick={() => onSelect(active ? 'all' : card.status)}
                        aria-pressed={active}
                        className={cn(
                            'rounded-xl border bg-card p-3 text-left transition-colors hover:bg-muted/40',
                            active && 'border-primary ring-1 ring-primary',
                        )}
                    >
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-muted-foreground">
                                {card.label}
                            </span>
                            <Icon
                                className={cn(
                                    'size-4',
                                    card.tone,
                                    card.status === 'processing' &&
                                        stats.processing > 0 &&
                                        'animate-spin motion-reduce:animate-none',
                                )}
                            />
                        </div>
                        <p className="mt-1 text-2xl font-semibold tracking-tight">
                            {formatNumber(stats[card.key])}
                        </p>
                    </button>
                );
            })}
        </div>
    );
}
