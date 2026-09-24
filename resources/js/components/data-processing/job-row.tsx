import { JobActions } from '@/components/data-processing/job-actions';
import { JobProgress } from '@/components/data-processing/job-progress';
import { JobStatusBadge } from '@/components/data-processing/job-status-badge';
import { JobTypeIcon } from '@/components/data-processing/job-type-icon';
import {
    computeEta,
    isActiveStatus,
    relativeTime,
    resultSummary,
} from '@/components/data-processing/job-utils';
import { Badge } from '@/components/ui/badge';
import type { DataProcessingJob } from '@/types';

type Props = {
    job: DataProcessingJob;
    onOpen: (job: DataProcessingJob) => void;
};

export function JobRow({ job, onOpen }: Props) {
    const meta = isActiveStatus(job.status) ? computeEta(job) : job.duration;

    return (
        <div className="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-muted/40">
            <JobTypeIcon icon={job.entity_icon ?? job.type_icon} />

            <button
                type="button"
                onClick={() => onOpen(job)}
                className="min-w-0 flex-1 text-left"
            >
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate font-medium">{job.name}</span>
                    <Badge variant="outline" className="font-normal">
                        {job.type_label}
                    </Badge>
                    {job.format_label && (
                        <span className="text-xs text-muted-foreground">
                            {job.format_label}
                        </span>
                    )}
                </div>

                <div className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                    <span>{relativeTime(job.created_at)}</span>
                    {job.owner && <span>· {job.owner}</span>}
                    {job.input_file_name && (
                        <span className="truncate">
                            · {job.input_file_name}
                        </span>
                    )}
                </div>

                {isActiveStatus(job.status) ? (
                    <JobProgress job={job} className="mt-2 max-w-md" />
                ) : (
                    <p className="mt-1 truncate text-xs text-muted-foreground">
                        {resultSummary(job)}
                    </p>
                )}
            </button>

            <div className="flex shrink-0 items-center gap-2">
                {meta && (
                    <span className="hidden text-xs text-muted-foreground sm:inline">
                        {meta}
                    </span>
                )}
                <JobStatusBadge status={job.status} label={job.status_label} />
                <JobActions job={job} onView={onOpen} />
            </div>
        </div>
    );
}
