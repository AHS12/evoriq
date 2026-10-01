import { router } from '@inertiajs/react';
import {
    Ban,
    Copy,
    Download,
    ExternalLink,
    Eye,
    MoreHorizontal,
    Play,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import {
    cancelConfirmation,
    deleteConfirmation,
} from '@/components/data-processing/job-recovery';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    cancel,
    destroy,
    duplicate,
    resume,
    retry,
    show,
} from '@/routes/activity';
import { download } from '@/routes/exports';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    job: DataProcessingJob;
    onView: (job: DataProcessingJob) => void;
};

export function JobActions({ job, onView }: Props) {
    const [confirming, setConfirming] = useState<'cancel' | 'delete' | null>(
        null,
    );
    const { t } = useTranslation();

    const post = (url: string): void => {
        router.post(url, {}, { preserveScroll: true, preserveState: true });
    };

    const remove = (): void => {
        router.delete(destroy.url(job.id), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const cancelCopy = cancelConfirmation(job, t);
    const deleteCopy = deleteConfirmation(job, t);

    return (
        <div className="flex items-center gap-1">
            {job.can.download && (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    aria-label={t('Download :name', { name: job.name })}
                >
                    <a href={download.url(job.id)}>
                        <Download className="size-4" />
                    </a>
                </Button>
            )}

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('Job actions')}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        onSelect={() => router.visit(show.url(job.id))}
                    >
                        <ExternalLink className="size-4" />
                        {t('Open run')}
                    </DropdownMenuItem>

                    <DropdownMenuItem onSelect={() => onView(job)}>
                        <Eye className="size-4" />
                        {t('View details')}
                    </DropdownMenuItem>

                    {job.can.duplicate && (
                        <DropdownMenuItem
                            onSelect={() => post(duplicate.url(job.id))}
                        >
                            <Copy className="size-4" />
                            {t('Run again')}
                        </DropdownMenuItem>
                    )}

                    {job.can.retry && (
                        <DropdownMenuItem
                            onSelect={() => post(retry.url(job.id))}
                        >
                            <RotateCcw className="size-4" />
                            {t('Retry')}
                        </DropdownMenuItem>
                    )}

                    {job.can.resume && (
                        <DropdownMenuItem
                            onSelect={() => post(resume.url(job.id))}
                        >
                            <Play className="size-4" />
                            {t('Resume')}
                        </DropdownMenuItem>
                    )}

                    {job.can.cancel && (
                        <DropdownMenuItem
                            onSelect={() => setConfirming('cancel')}
                        >
                            <Ban className="size-4" />
                            {t('Stop')}
                        </DropdownMenuItem>
                    )}

                    {job.can.delete && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setConfirming('delete')}
                            >
                                <Trash2 className="size-4" />
                                {t('Delete')}
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={cancelCopy.title}
                description={cancelCopy.description}
                confirmLabel={t('Stop')}
                destructive
                onConfirm={() => {
                    setConfirming(null);
                    post(cancel.url(job.id));
                }}
            />

            <ConfirmDialog
                open={confirming === 'delete'}
                onOpenChange={(open) => setConfirming(open ? 'delete' : null)}
                title={deleteCopy.title}
                description={deleteCopy.description}
                confirmLabel={t('Delete')}
                destructive
                onConfirm={() => {
                    setConfirming(null);
                    remove();
                }}
            />
        </div>
    );
}
