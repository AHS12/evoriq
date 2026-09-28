import { router } from '@inertiajs/react';
import { Ban, Copy, Download, RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import { cancel, destroy, duplicate, retry } from '@/routes/activity';
import { download } from '@/routes/exports';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

/**
 * PIPE-05 — the status-aware action bar. Mechanics and confirmations beyond a
 * plain stop/retry are PIPE-07's concern.
 */
export function RunActionBar({ run }: Props) {
    const { t } = useTranslation();
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const post = (url: string): void => {
        router.post(url, {}, { preserveScroll: true, preserveState: true });
    };

    const hasPrimary =
        run.abilities.cancel ||
        run.abilities.retry ||
        run.abilities.duplicate ||
        run.abilities.resume;

    if (!hasPrimary && !run.abilities.download && !run.abilities.delete) {
        return null;
    }

    return (
        <div
            data-slot="run-action-bar"
            className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-card p-3"
        >
            <div className="flex flex-wrap items-center gap-2">
                {run.abilities.cancel && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => post(cancel.url(run.id))}
                    >
                        <Ban className="size-4" />
                        {t('Stop')}
                    </Button>
                )}

                {run.abilities.retry && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => post(retry.url(run.id))}
                    >
                        <RotateCcw className="size-4" />
                        {t('Retry')}
                    </Button>
                )}

                {run.abilities.duplicate && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => post(duplicate.url(run.id))}
                    >
                        <Copy className="size-4" />
                        {t('Run again')}
                    </Button>
                )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {run.abilities.download && run.download_url && (
                    <Button asChild variant="outline" size="sm">
                        {/* Streamed file — a native anchor, not Inertia Link. */}
                        <a href={download.url(run.id)}>
                            <Download className="size-4" />
                            {t('Download')}
                        </a>
                    </Button>
                )}

                {run.abilities.delete && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => setConfirmingDelete(true)}
                    >
                        <Trash2 className="size-4" />
                        {t('Delete')}
                    </Button>
                )}
            </div>

            <ConfirmDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                title={t('Delete job?')}
                description={t(
                    '":name" and its files will be permanently deleted.',
                    { name: run.name },
                )}
                confirmLabel={t('Delete')}
                destructive
                onConfirm={() => {
                    setConfirmingDelete(false);
                    router.delete(destroy.url(run.id), {
                        preserveScroll: true,
                        preserveState: true,
                    });
                }}
            />
        </div>
    );
}
