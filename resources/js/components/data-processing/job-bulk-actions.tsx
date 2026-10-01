import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { retryFailed } from '@/routes/activity';
import { useTranslation } from '@/hooks/use-translation';
import { toast } from '@/lib/toast';
import type { DataProcessingJob } from '@/types';

type Props = {
    jobs: DataProcessingJob[];
    selected: number[];
    onClear: () => void;
};

/**
 * PIPE-07 — bulk recovery for the failed view: retry every final run in the
 * filtered set, or just the selected subset.
 */
export function JobBulkActions({ jobs, selected, onClear }: Props) {
    const { t } = useTranslation();
    const retryable = jobs.filter((job) => job.abilities.retry);

    if (retryable.length === 0 && selected.length === 0) {
        return null;
    }

    const retry = (ids: number[]): void => {
        if (ids.length === 0) {
            return;
        }

        router.post(
            retryFailed.url(),
            { ids },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    onClear();
                    toast.success(t('Queued :count…', { count: ids.length }));
                },
            },
        );
    };

    return (
        <div
            data-slot="job-bulk-actions"
            className="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/30 px-3 py-2"
        >
            <span className="text-xs text-muted-foreground">
                {t(':count selected', { count: selected.length })}
            </span>

            <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => retry(retryable.map((job) => job.id))}
            >
                <RotateCcw className="size-4" />
                {t('Retry all failed')}
            </Button>

            {selected.length > 0 && (
                <Button type="button" size="sm" onClick={() => retry(selected)}>
                    {t('Retry selected')}
                </Button>
            )}

            {selected.length > 0 && (
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={onClear}
                >
                    {t('Clear selection')}
                </Button>
            )}
        </div>
    );
}
