import { cn } from '@/lib/utils';
import { formatNumber } from '@/components/data-processing/job-utils';
import type { DataProcessingJob } from '@/types';

type Props = {
    job: DataProcessingJob;
    className?: string;
};

export function JobProgress({ job, className }: Props) {
    const { total, processed, percentage, indeterminate } = job.progress;

    return (
        <div className={cn('space-y-1.5', className)}>
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={indeterminate ? undefined : percentage}
                aria-label={`${job.name} progress`}
                className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full bg-primary transition-[width] duration-500 ease-out motion-reduce:transition-none',
                        indeterminate &&
                            'animate-pulse motion-reduce:animate-none',
                    )}
                    style={{ width: indeterminate ? '100%' : `${percentage}%` }}
                />
            </div>
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>
                    {indeterminate
                        ? (job.stage ?? 'Processing…')
                        : `${formatNumber(processed)} / ${formatNumber(total)}`}
                </span>
                {!indeterminate && <span>{percentage}%</span>}
            </div>
        </div>
    );
}
