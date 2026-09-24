import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { DataProcessingJob, JobStatus } from '@/types';

const FINAL_STATUSES: JobStatus[] = ['completed', 'failed', 'cancelled'];

/**
 * Watches the job list and fires a toast when a job transitions into a final
 * state (completed / failed / cancelled).
 */
export function useJobTransitions(jobs: DataProcessingJob[]): void {
    const previous = useRef<Map<number, JobStatus>>(new Map());
    const initialised = useRef(false);

    useEffect(() => {
        if (!initialised.current) {
            initialised.current = true;
            previous.current = new Map(jobs.map((job) => [job.id, job.status]));

            return;
        }

        jobs.forEach((job) => {
            const before = previous.current.get(job.id);

            if (
                before !== undefined &&
                before !== job.status &&
                FINAL_STATUSES.includes(job.status)
            ) {
                const description =
                    job.status === 'failed'
                        ? (job.error_message ?? undefined)
                        : job.status_label;

                if (job.status === 'failed') {
                    toast.error(job.name, { description });
                } else if (job.status === 'cancelled') {
                    toast.info(job.name, { description });
                } else {
                    toast.success(job.name, { description });
                }
            }
        });

        previous.current = new Map(jobs.map((job) => [job.id, job.status]));
    }, [jobs]);
}
