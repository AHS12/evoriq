import { Download } from 'lucide-react';
import { JobIssueTable } from '@/components/data-processing/job-issue-table';
import { JobProgress } from '@/components/data-processing/job-progress';
import { JobStatusBadge } from '@/components/data-processing/job-status-badge';
import { JobTypeIcon } from '@/components/data-processing/job-type-icon';
import {
    formatBytes,
    formatNumber,
    relativeTime,
} from '@/components/data-processing/job-utils';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { download } from '@/routes/exports';
import type { DataProcessingJob } from '@/types';

type Props = {
    job: DataProcessingJob | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function MetaRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

export function JobDetailSheet({ job, open, onOpenChange }: Props) {
    const skipped =
        job?.errors.filter((error) => error.type === 'duplicate').length ?? 0;

    const parameters = job?.parameters
        ? Object.entries(job.parameters).filter(
              ([, value]) => value !== null && value !== '',
          )
        : [];

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
                            <div className="flex flex-wrap items-center gap-2 pt-1">
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
                        </SheetHeader>

                        <div className="space-y-5 px-4 pb-6">
                            {job.status === 'processing' && (
                                <JobProgress job={job} />
                            )}

                            <section className="space-y-2">
                                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Timeline
                                </h3>
                                <div className="space-y-1">
                                    <MetaRow
                                        label="Created"
                                        value={relativeTime(job.created_at)}
                                    />
                                    {job.started_at && (
                                        <MetaRow
                                            label="Started"
                                            value={relativeTime(job.started_at)}
                                        />
                                    )}
                                    {job.completed_at && (
                                        <MetaRow
                                            label={
                                                job.status === 'failed'
                                                    ? 'Failed'
                                                    : 'Finished'
                                            }
                                            value={relativeTime(
                                                job.completed_at,
                                            )}
                                        />
                                    )}
                                    {job.duration && (
                                        <MetaRow
                                            label="Duration"
                                            value={job.duration}
                                        />
                                    )}
                                </div>
                            </section>

                            <Separator />

                            <section className="space-y-2">
                                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Result
                                </h3>
                                <div className="space-y-1">
                                    {job.type === 'import' ? (
                                        <>
                                            <MetaRow
                                                label="Created"
                                                value={formatNumber(
                                                    job.counts.created ??
                                                        job.success_count,
                                                )}
                                            />
                                            <MetaRow
                                                label="Skipped"
                                                value={formatNumber(skipped)}
                                            />
                                            <MetaRow
                                                label="Failed"
                                                value={formatNumber(
                                                    job.counts.failed ??
                                                        job.error_count,
                                                )}
                                            />
                                        </>
                                    ) : (
                                        <MetaRow
                                            label="Rows"
                                            value={formatNumber(
                                                job.processed_items,
                                            )}
                                        />
                                    )}
                                </div>
                            </section>

                            {job.artifacts.length > 0 && (
                                <>
                                    <Separator />
                                    <section className="space-y-2">
                                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Artifact
                                        </h3>
                                        {job.artifacts.map((artifact) => (
                                            <div
                                                key={artifact.key}
                                                className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium">
                                                        {artifact.label}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {artifact.file_name ??
                                                            '—'}{' '}
                                                        ·{' '}
                                                        {formatBytes(
                                                            artifact.size,
                                                        )}
                                                    </p>
                                                </div>
                                                {artifact.downloadable &&
                                                    job.can.download && (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <a
                                                                href={download.url(
                                                                    job.id,
                                                                )}
                                                            >
                                                                <Download className="size-4" />
                                                                Download
                                                            </a>
                                                        </Button>
                                                    )}
                                            </div>
                                        ))}
                                    </section>
                                </>
                            )}

                            {parameters.length > 0 && (
                                <>
                                    <Separator />
                                    <section className="space-y-2">
                                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Parameters
                                        </h3>
                                        <div className="space-y-1">
                                            {parameters.map(([key, value]) => (
                                                <MetaRow
                                                    key={key}
                                                    label={key.replace(
                                                        /_/g,
                                                        ' ',
                                                    )}
                                                    value={String(value)}
                                                />
                                            ))}
                                        </div>
                                    </section>
                                </>
                            )}

                            {job.errors.length > 0 && (
                                <>
                                    <Separator />
                                    <section className="space-y-2">
                                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            Issues ({job.errors.length})
                                        </h3>
                                        <JobIssueTable errors={job.errors} />
                                    </section>
                                </>
                            )}

                            <div className="pt-2">
                                <p className="text-xs break-all text-muted-foreground">
                                    Job ID · {job.job_id}
                                </p>
                            </div>
                        </div>
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
