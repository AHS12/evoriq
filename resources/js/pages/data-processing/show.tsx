import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pause, Play } from 'lucide-react';
import { useEffect, useState } from 'react';
import { isActiveStatus } from '@/components/data-processing/job-utils';
import { mergeEvents } from '@/components/data-processing/run-event-utils';
import {
    RunInspector,
    type InspectorTab,
} from '@/components/data-processing/run-inspector';
import { RunActionBar } from '@/components/data-processing/run-action-bar';
import { RunEventStream } from '@/components/data-processing/run-event-stream';
import { RunHero } from '@/components/data-processing/run-hero';
import { RunTerminalSummary } from '@/components/data-processing/run-terminal-summary';
import { Button } from '@/components/ui/button';
import { useLivePoll } from '@/hooks/use-live-poll';
import { useTranslation } from '@/hooks/use-translation';
import { useUrlTab } from '@/hooks/use-url-tab';
import {
    index as activityIndex,
    show as activityShow,
} from '@/routes/activity';
import type {
    DataProcessingJob,
    EventsMeta,
    JobOptions,
    JobTimelineEvent,
} from '@/types';

type Props = {
    run: DataProcessingJob;
    events: JobTimelineEvent[];
    eventsMeta: EventsMeta;
    options: JobOptions;
};

const INSPECTOR_TABS: readonly InspectorTab[] = [
    'timeline',
    'overview',
    'issues',
    'artifacts',
    'parameters',
    'advanced',
];

export default function RunShow({
    run,
    events: incomingEvents,
    eventsMeta,
}: Props) {
    const { t } = useTranslation();

    const [events, setEvents] = useState<JobTimelineEvent[]>(incomingEvents);
    const [hasMoreOlder, setHasMoreOlder] = useState(eventsMeta.has_more_older);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [tab, setTab] = useUrlTab<InspectorTab>(
        'tab',
        'timeline',
        INSPECTOR_TABS,
    );

    const active = isActiveStatus(run.status);

    const { live, pause, resume } = useLivePoll({
        url: activityShow.url(run.id),
        only: ['run', 'events', 'eventsMeta', 'pipeline_revision'],
        enabled: active,
        idleInterval: active ? 15000 : 0,
    });

    // Merge every polled tail (append) or older page (prepend) into the set.
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

    const hideTab =
        (tab === 'advanced' && run.advanced === null) ||
        (tab === 'issues' && run.issues.length === 0) ||
        (tab === 'artifacts' && run.artifacts.length === 0);
    const safeTab: InspectorTab = hideTab ? 'timeline' : tab;

    return (
        <>
            <Head title={run.name} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="sticky top-0 z-20 -mx-4 flex flex-wrap items-center justify-between gap-2 border-b bg-background/90 px-4 py-2 backdrop-blur">
                    <Link
                        href={activityIndex.url()}
                        className="inline-flex items-center gap-1 rounded text-sm text-muted-foreground transition-smooth-fast hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" />
                        {t('Job activity')}
                    </Link>

                    {active && (
                        <Button
                            type="button"
                            size="sm"
                            variant={live ? 'outline' : 'secondary'}
                            onClick={() => (live ? pause() : resume())}
                        >
                            {live ? (
                                <Pause className="size-3.5" />
                            ) : (
                                <Play className="size-3.5" />
                            )}
                            {live ? t('Pause live') : t('Resume live')}
                        </Button>
                    )}
                </div>

                <RunHero run={run} />
                <RunTerminalSummary
                    run={run}
                    canManage={run.advanced !== null}
                />

                <RunInspector
                    run={run}
                    events={events}
                    value={safeTab}
                    onValueChange={setTab}
                    timeline={
                        <RunEventStream
                            run={run}
                            events={events}
                            hasMoreOlder={hasMoreOlder}
                            loadingOlder={loadingOlder}
                            onLoadOlder={loadOlder}
                        />
                    }
                />

                <RunActionBar run={run} />
            </div>
        </>
    );
}
