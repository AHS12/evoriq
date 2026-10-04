import { RunEventStream } from '@/components/data-processing/run-event-stream';
import { RunHero } from '@/components/data-processing/run-hero';
import { StageLanes } from '@/components/data-processing/stage-lanes';
import type { DataProcessingJob, JobTimelineEvent } from '@/types';

type Props = {
    run: DataProcessingJob;
    events: JobTimelineEvent[];
    hasMoreOlder: boolean;
    loadingOlder: boolean;
    onLoadOlder: () => void;
};

/**
 * PIPE-09 step 4 — the live import experience: the PIPE-05 run hero, stage
 * lanes and streaming timeline, reused verbatim for a Clockify sync run
 * (SYNC-14).
 */
export function ImportRunPanel({
    run,
    events,
    hasMoreOlder,
    loadingOlder,
    onLoadOlder,
}: Props) {
    return (
        <div className="space-y-4">
            <RunHero run={run} />
            <StageLanes run={run} events={events} />
            <RunEventStream
                run={run}
                events={events}
                hasMoreOlder={hasMoreOlder}
                loadingOlder={loadingOlder}
                onLoadOlder={onLoadOlder}
            />
        </div>
    );
}
