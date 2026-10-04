import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { isActiveStatus } from '@/components/data-processing/job-utils';
import { mergeEvents } from '@/components/data-processing/run-event-utils';
import { ImportComplete } from '@/components/import/import-complete';
import { ImportRunPanel } from '@/components/import/import-run-panel';
import { useLivePoll } from '@/hooks/use-live-poll';
import { useTranslation } from '@/hooks/use-translation';
import { index as importIndex, show as importShow } from '@/routes/import';
import type { DataProcessingJob, EventsMeta, JobTimelineEvent } from '@/types';

type Props = {
    run: DataProcessingJob;
    events: JobTimelineEvent[];
    eventsMeta: EventsMeta;
};

/**
 * PIPE-09 / ENT-14 — the deep-linkable live import run. Reuses the PIPE-05
 * components and polls the run's event stream while it is active.
 */
export default function ImportShow({
    run,
    events: incomingEvents,
    eventsMeta,
}: Props) {
    const { t } = useTranslation();

    const [events, setEvents] = useState<JobTimelineEvent[]>(incomingEvents);
    const [hasMoreOlder, setHasMoreOlder] = useState(eventsMeta.has_more_older);
    const [loadingOlder, setLoadingOlder] = useState(false);

    const active = isActiveStatus(run.status);

    useLivePoll({
        url: importShow.url(run.id),
        only: ['run', 'events', 'eventsMeta'],
        enabled: active,
        idleInterval: active ? 15000 : 0,
    });

    useEffect(() => {
        setEvents((previous) => mergeEvents(previous, incomingEvents));
    }, [incomingEvents]);

    const loadOlder = (): void => {
        const oldest = events[0]?.sequence;

        if (!oldest || loadingOlder) {
            return;
        }

        setLoadingOlder(true);

        router.reload({
            data: { before_sequence: oldest },
            only: ['events', 'eventsMeta'],
            preserveUrl: true,
            replace: true,
            showProgress: false,
            onSuccess: (page) => {
                const meta = page.props.eventsMeta as EventsMeta | undefined;
                setHasMoreOlder(Boolean(meta?.has_more_older));
            },
            onFinish: () => setLoadingOlder(false),
        });
    };

    return (
        <>
            <Head title={run.name} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="sticky top-0 z-20 -mx-4 border-b bg-background/90 px-4 py-2 backdrop-blur">
                    <Link
                        href={importIndex.url()}
                        className="inline-flex items-center gap-1 rounded text-sm text-muted-foreground transition-smooth-fast hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        {t('Historical import')}
                    </Link>
                </div>

                <ImportRunPanel
                    run={run}
                    events={events}
                    hasMoreOlder={hasMoreOlder}
                    loadingOlder={loadingOlder}
                    onLoadOlder={loadOlder}
                />

                {run.status === 'completed' && <ImportComplete run={run} />}
            </div>
        </>
    );
}

ImportShow.layout = {
    breadcrumbs: [{ title: 'Historical import', href: importIndex() }],
};
