import { router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index } from '@/routes/activity';
import type { QueuedJob } from '@/types';

/**
 * App-wide confirmation shown after any job is queued. Driven by the Inertia
 * `job_queued` flash, so every producer (exports, imports, future reports)
 * gets the same experience.
 */
export function JobQueuedDialog() {
    const [queued, setQueued] = useState<QueuedJob | null>(null);

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flash = (event as CustomEvent).detail?.flash;
                const data = flash?.job_queued as QueuedJob | undefined;

                if (data?.job_id) {
                    setQueued(data);
                }
            }),
        [],
    );

    const close = (): void => setQueued(null);

    const viewUrl = queued
        ? index.url({ query: { job_id: queued.job_id } })
        : index.url();

    return (
        <Dialog
            open={queued !== null}
            onOpenChange={(open) => {
                if (!open) {
                    close();
                }
            }}
        >
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="items-center text-center">
                    <div className="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-500/10">
                        <CheckCircle2 className="size-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <DialogTitle>Your job is queued</DialogTitle>
                    <DialogDescription>
                        {queued
                            ? `“${queued.name}” is running`
                            : 'It is running'}{' '}
                        in the background. Follow its progress in Job activity —
                        we'll notify you when it's ready.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="sm:justify-center">
                    <Button variant="outline" onClick={close}>
                        Close
                    </Button>
                    <Button asChild onClick={close}>
                        <a href={viewUrl}>View job activity</a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
