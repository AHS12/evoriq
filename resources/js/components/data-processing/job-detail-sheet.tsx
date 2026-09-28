import { router } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import { JobStatusBadge } from '@/components/data-processing/job-status-badge';
import { JobTypeIcon } from '@/components/data-processing/job-type-icon';
import { RunInspector } from '@/components/data-processing/run-inspector';
import { relativeTime } from '@/components/data-processing/job-utils';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { show } from '@/routes/activity';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    job: DataProcessingJob | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * PIPE-06 — the quick-peek sheet. It shares the tabbed {@see RunInspector} with
 * the full run page; Advanced lives only on the page.
 */
export function JobDetailSheet({ job, open, onOpenChange }: Props) {
    const { t } = useTranslation();

    return (
        <Sheet open={open && job !== null} onOpenChange={onOpenChange}>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                {job && (
                    <>
                        <SheetHeader>
                            <div className="flex items-start gap-3">
                                <JobTypeIcon
                                    icon={job.entity_icon ?? job.type_icon}
                                />
                                <div className="min-w-0 flex-1">
                                    <SheetTitle className="truncate">
                                        {job.name}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {job.type_label}
                                        {job.format_label
                                            ? ` · ${job.format_label}`
                                            : ''}{' '}
                                        · {relativeTime(job.created_at)}
                                    </SheetDescription>
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <JobStatusBadge
                                        status={job.status}
                                        label={job.status_label}
                                    />
                                    {job.stage && (
                                        <span className="text-xs text-muted-foreground">
                                            {job.stage}
                                        </span>
                                    )}
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.visit(show.url(job.id))
                                    }
                                >
                                    <ExternalLink className="size-4" />
                                    {t('Open full run')}
                                </Button>
                            </div>
                        </SheetHeader>

                        <div className="px-4 pb-6">
                            <RunInspector run={job} variant="sheet" />

                            <p className="mt-6 text-xs break-all text-muted-foreground">
                                {t('Job ID')} · {job.job_id}
                            </p>
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
