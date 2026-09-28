import { Link } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { JobProgress } from '@/components/data-processing/job-progress';
import { JobStatusBadge } from '@/components/data-processing/job-status-badge';
import { JobTypeIcon } from '@/components/data-processing/job-type-icon';
import { show } from '@/routes/activity';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    runs: DataProcessingJob[];
};

/**
 * PIPE-05 — the "Active now" section on the Job activity list: live mini run
 * cards linking to each run's dedicated timeline page.
 */
export function ActiveRuns({ runs }: Props) {
    const { t } = useTranslation();

    if (runs.length === 0) {
        return null;
    }

    return (
        <section data-slot="active-runs" className="space-y-2">
            <h2 className="flex items-center gap-1.5 text-sm font-medium">
                <Activity className="size-4 text-muted-foreground" />
                {t('Active now')}
            </h2>

            <div className="grid gap-3 sm:grid-cols-2">
                {runs.map((run) => (
                    <Link
                        key={run.id}
                        href={show.url(run.id)}
                        className="block rounded-xl border bg-card p-3 transition-smooth hover:border-primary/40"
                    >
                        <div className="flex items-center gap-2">
                            <JobTypeIcon
                                icon={run.entity_icon ?? run.type_icon}
                            />
                            <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                {run.name}
                            </span>
                            <JobStatusBadge
                                status={run.status}
                                label={run.status_label}
                            />
                        </div>

                        <JobProgress job={run} className="mt-2" />

                        <p className="mt-1 truncate text-xs text-muted-foreground">
                            {run.stage ?? run.status_label}
                        </p>
                    </Link>
                ))}
            </div>
        </section>
    );
}
