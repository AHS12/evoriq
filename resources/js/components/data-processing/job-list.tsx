import { useMemo } from 'react';
import { JobRow } from '@/components/data-processing/job-row';
import { groupJobsByDay } from '@/components/data-processing/job-utils';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    jobs: DataProcessingJob[];
    onOpen: (job: DataProcessingJob) => void;
    selectable?: boolean;
    selectedIds?: number[];
    onToggle?: (id: number) => void;
};

export function JobList({
    jobs,
    onOpen,
    selectable = false,
    selectedIds = [],
    onToggle,
}: Props) {
    const { t } = useTranslation();
    const groups = useMemo(() => groupJobsByDay(jobs, t), [jobs, t]);
    const selected = useMemo(() => new Set(selectedIds), [selectedIds]);

    return (
        <div className="overflow-hidden rounded-xl border">
            {groups.map((group) => (
                <div key={group.label}>
                    <div className="sticky top-0 z-10 border-y bg-muted/60 px-4 py-1.5 text-xs font-medium text-muted-foreground backdrop-blur first:border-t-0">
                        {group.label}
                    </div>
                    <div className="divide-y">
                        {group.jobs.map((job) => (
                            <JobRow
                                key={job.id}
                                job={job}
                                onOpen={onOpen}
                                selectable={selectable}
                                selected={selected.has(job.id)}
                                onToggle={onToggle}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
