import { computeEta } from '@/components/data-processing/job-utils';
import { formatDuration, formatNumber } from '@/lib/format';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
    className?: string;
};

/**
 * PIPE-05 — the run hero's progress block: a `role="progressbar"` with an
 * accessible value text plus the supporting metrics (rows, throughput, ETA,
 * elapsed, stage).
 */
export function RunProgress({ run, className }: Props) {
    const { t } = useTranslation();
    const {
        total,
        processed,
        percentage,
        indeterminate,
        throughput_per_min,
        elapsed_seconds,
    } = run.progress;

    const eta = computeEta(run, t);
    const valueText = indeterminate
        ? (run.stage ?? t('Processing…'))
        : t(':processed of :total rows', {
              processed: formatNumber(processed),
              total: formatNumber(total),
          });

    const metrics = [
        !indeterminate &&
            t(':processed / :total rows', {
                processed: formatNumber(processed),
                total: formatNumber(total),
            }),
        throughput_per_min != null &&
            `${formatNumber(throughput_per_min)} ${t('rows/min')}`,
        eta,
        t(':duration elapsed', {
            duration: formatDuration(elapsed_seconds),
        }),
    ].filter((value): value is string => Boolean(value));

    return (
        <div className={cn('space-y-2', className)}>
            <div
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={indeterminate ? undefined : percentage}
                aria-valuetext={valueText}
                className="h-2 w-full overflow-hidden rounded-full bg-muted"
            >
                <div
                    className={cn(
                        'h-full rounded-full bg-primary transition-[width] duration-slow ease-standard motion-reduce:transition-none',
                        indeterminate &&
                            'animate-pulse motion-reduce:animate-none',
                    )}
                    style={{
                        width: indeterminate ? '100%' : `${percentage}%`,
                    }}
                />
            </div>

            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    {metrics.map((metric, index) => (
                        <span key={index} className="tabular-nums">
                            {metric}
                        </span>
                    ))}
                </div>
                {!indeterminate && (
                    <span className="font-medium text-foreground">
                        {percentage}%
                    </span>
                )}
            </div>

            {run.stage && (
                <p className="text-xs text-muted-foreground">
                    {t('Stage: :stage', { stage: run.stage })}
                </p>
            )}
        </div>
    );
}
