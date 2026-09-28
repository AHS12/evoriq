import {
    useCallback,
    useEffect,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { ArrowDown, Pause, Play, Search } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import {
    collapseProgress,
    eventDescription,
    eventIcon,
    eventTitle,
    eventTone,
    filterEvents,
    hasContext,
    latestAnnouncement,
} from '@/components/data-processing/run-event-utils';
import { RunJsonViewer } from '@/components/data-processing/run-json-viewer';
import { isActiveStatus } from '@/components/data-processing/job-utils';
import { LiveRegion } from '@/components/feedback/live-region';
import { Timeline } from '@/components/timeline/timeline';
import {
    TimelineContent,
    TimelineItem,
} from '@/components/timeline/timeline-item';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type {
    DataProcessingJob,
    JobTimelineEvent,
    RunEventFilter,
} from '@/types';

type Props = {
    run: DataProcessingJob;
    events: JobTimelineEvent[];
    hasMoreOlder: boolean;
    loadingOlder: boolean;
    onLoadOlder: () => void;
};

const FILTERS: RunEventFilter[] = ['all', 'stages', 'issues'];

const FILTER_LABEL: Record<RunEventFilter, string> = {
    all: 'All',
    stages: 'Stages',
    issues: 'Issues',
};

function EventContext({ event }: { event: JobTimelineEvent }) {
    return (
        <RunJsonViewer
            value={event.context}
            truncated={event.context_truncated}
        />
    );
}

/**
 * PIPE-05 — the live event timeline: filters, search, auto-follow, "load
 * older" on scroll-up and a polite live region for meaningful announcements.
 * New events are deduped by sequence upstream (`mergeEvents`), so an unchanged
 * poll never re-animates existing rows.
 */
export function RunEventStream({
    run,
    events,
    hasMoreOlder,
    loadingOlder,
    onLoadOlder,
}: Props) {
    const { t } = useTranslation();
    const [filter, setFilter] = useState<RunEventFilter>('all');
    const [search, setSearch] = useState('');
    const [follow, setFollow] = useState(true);

    const scrollRef = useRef<HTMLDivElement | null>(null);
    const searchRef = useRef<HTMLInputElement | null>(null);
    const lastHeight = useRef(0);
    const prevOldest = useRef<number | null>(null);

    const visible = useMemo(
        () => filterEvents(collapseProgress(events), filter, search),
        [events, filter, search],
    );
    const announcement = useMemo(() => latestAnnouncement(events), [events]);

    const scrollToBottom = useCallback((behavior: ScrollBehavior = 'auto') => {
        const element = scrollRef.current;

        if (element) {
            element.scrollTo({ top: element.scrollHeight, behavior });
        }
    }, []);

    // Keep the viewport stable when older events are prepended, and pinned to
    // the bottom while following new ones.
    useLayoutEffect(() => {
        const element = scrollRef.current;

        if (!element) {
            return;
        }

        const oldest = events[0]?.sequence ?? null;
        const prepended =
            prevOldest.current !== null &&
            oldest !== null &&
            oldest < prevOldest.current;

        if (prepended) {
            element.scrollTop += element.scrollHeight - lastHeight.current;
        } else if (follow) {
            element.scrollTop = element.scrollHeight;
        }

        prevOldest.current = oldest;
        lastHeight.current = element.scrollHeight;
    }, [events, follow]);

    // Land on the newest event on first paint (auto-follow), after layout
    // settles (fonts/rows), so the stream does not open part-way down.
    useEffect(() => {
        if (!follow) {
            return;
        }

        const frame = requestAnimationFrame(() => scrollToBottom());

        return () => cancelAnimationFrame(frame);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        const onKeyDown = (keyboardEvent: KeyboardEvent): void => {
            const target = keyboardEvent.target as HTMLElement | null;
            const typing =
                target !== null &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.isContentEditable);

            if (typing) {
                return;
            }

            if (keyboardEvent.key === '/') {
                keyboardEvent.preventDefault();
                searchRef.current?.focus();
            } else if (keyboardEvent.key === 'f') {
                setFollow((value) => !value);
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    const handleScroll = (): void => {
        const element = scrollRef.current;

        if (!element) {
            return;
        }

        const distanceFromBottom =
            element.scrollHeight - element.scrollTop - element.clientHeight;
        setFollow(distanceFromBottom < 48);

        if (element.scrollTop < 48 && hasMoreOlder && !loadingOlder) {
            onLoadOlder();
        }
    };

    return (
        <section className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-medium">{t('Live timeline')}</h2>

                <div className="flex flex-wrap items-center gap-2">
                    <div
                        role="group"
                        aria-label={t('Filter events')}
                        className="flex items-center gap-1"
                    >
                        {FILTERS.map((value) => (
                            <Button
                                key={value}
                                type="button"
                                size="sm"
                                variant={
                                    filter === value ? 'secondary' : 'ghost'
                                }
                                aria-pressed={filter === value}
                                onClick={() => setFilter(value)}
                            >
                                {t(FILTER_LABEL[value])}
                            </Button>
                        ))}
                    </div>

                    <div className="relative">
                        <Search
                            aria-hidden
                            className="absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            ref={searchRef}
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search events')}
                            aria-label={t('Search events')}
                            className="h-8 w-44 pl-7 text-xs"
                        />
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        variant={follow ? 'secondary' : 'outline'}
                        aria-pressed={follow}
                        onClick={() => {
                            setFollow((value) => {
                                const next = !value;

                                if (next) {
                                    scrollToBottom();
                                }

                                return next;
                            });
                        }}
                    >
                        {follow ? (
                            <Pause className="size-3.5" />
                        ) : (
                            <Play className="size-3.5" />
                        )}
                        {follow ? t('Following') : t('Follow')}
                    </Button>
                </div>
            </div>

            <div
                ref={scrollRef}
                onScroll={handleScroll}
                data-slot="run-event-stream"
                className="max-h-[60vh] overflow-y-auto rounded-xl border bg-card p-4"
            >
                {hasMoreOlder && (
                    <div className="mb-3 flex justify-center">
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            loading={loadingOlder}
                            onClick={onLoadOlder}
                        >
                            {t('Load older events')}
                        </Button>
                    </div>
                )}

                {visible.length === 0 ? (
                    <EmptyState
                        variant="compact"
                        title={
                            events.length === 0
                                ? t('Waiting for the first step…')
                                : t('No events match your filters.')
                        }
                    />
                ) : (
                    <Timeline aria-label={t('Run events')}>
                        {visible.map((event) => {
                            const Icon = eventIcon(event);
                            const showContext = hasContext(event);

                            return (
                                <TimelineItem
                                    key={event.id}
                                    tone={eventTone(event)}
                                    marker={<Icon className="size-3.5" />}
                                    time={event.occurred_at}
                                    durationMs={event.duration_ms}
                                    expandable={showContext}
                                >
                                    <TimelineContent
                                        title={eventTitle(event, t)}
                                        description={
                                            eventDescription(event) ?? undefined
                                        }
                                    >
                                        {showContext ? (
                                            <EventContext event={event} />
                                        ) : null}
                                    </TimelineContent>
                                </TimelineItem>
                            );
                        })}
                    </Timeline>
                )}
            </div>

            {!follow && (
                <div className={cn('flex justify-center')}>
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => {
                            setFollow(true);
                            scrollToBottom('smooth');
                        }}
                    >
                        <ArrowDown className="size-3.5" />
                        {t('Jump to latest')}
                    </Button>
                </div>
            )}

            <LiveRegion
                message={announcement ?? undefined}
                busy={isActiveStatus(run.status)}
            />
        </section>
    );
}
