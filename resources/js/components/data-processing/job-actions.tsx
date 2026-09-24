import { router } from '@inertiajs/react';
import {
    Ban,
    Copy,
    Download,
    Eye,
    MoreHorizontal,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cancel, destroy, duplicate, retry } from '@/routes/activity';
import { download } from '@/routes/exports';
import type { DataProcessingJob } from '@/types';

type Props = {
    job: DataProcessingJob;
    onView: (job: DataProcessingJob) => void;
};

export function JobActions({ job, onView }: Props) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    const post = (url: string): void => {
        router.post(url, {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <div className="flex items-center gap-1">
            {job.can.download && (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    aria-label={`Download ${job.name}`}
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
                        aria-label="Job actions"
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onSelect={() => onView(job)}>
                        <Eye className="size-4" />
                        View details
                    </DropdownMenuItem>

                    {job.can.duplicate && (
                        <DropdownMenuItem
                            onSelect={() => post(duplicate.url(job.id))}
                        >
                            <Copy className="size-4" />
                            Run again
                        </DropdownMenuItem>
                    )}

                    {job.can.retry && (
                        <DropdownMenuItem
                            onSelect={() => post(retry.url(job.id))}
                        >
                            <RotateCcw className="size-4" />
                            Retry
                        </DropdownMenuItem>
                    )}

                    {job.can.cancel && (
                        <DropdownMenuItem
                            onSelect={() => post(cancel.url(job.id))}
                        >
                            <Ban className="size-4" />
                            Stop
                        </DropdownMenuItem>
                    )}

                    {job.can.delete && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setConfirmingDelete(true)}
                            >
                                <Trash2 className="size-4" />
                                Delete
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            <ConfirmDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                title="Delete job?"
                description={`"${job.name}" and its files will be permanently deleted.`}
                confirmLabel="Delete"
                destructive
                onConfirm={() => {
                    setConfirmingDelete(false);
                    router.delete(destroy.url(job.id), {
                        preserveScroll: true,
                        preserveState: true,
                    });
                }}
            />
        </div>
    );
}
