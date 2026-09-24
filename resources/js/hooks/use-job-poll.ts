import { usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * How often the Job Center refreshes while work is in progress.
 */
const ACTIVE_INTERVAL = 3000;

/**
 * Keeps the Job Center fresh by polling the shared page props.
 *
 * Polling only runs while there is active work, the tab is visible and the
 * user has not paused it. Returns a `live` flag plus pause/resume controls so
 * the UI can show a "Live / Paused" state.
 */
export function useJobPoll(active: boolean) {
    const [live, setLive] = useState(true);

    const { start, stop } = usePoll(
        ACTIVE_INTERVAL,
        { only: ['jobs', 'stats', 'activeJobs'] },
        { autoStart: false },
    );

    const startRef = useRef(start);
    const stopRef = useRef(stop);
    const activeRef = useRef(active);
    const liveRef = useRef(live);

    startRef.current = start;
    stopRef.current = stop;
    activeRef.current = active;
    liveRef.current = live;

    useEffect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const update = (): void => {
            if (document.hidden || !activeRef.current || !liveRef.current) {
                stopRef.current();
            } else {
                startRef.current();
            }
        };

        update();
        document.addEventListener('visibilitychange', update);

        return () => {
            document.removeEventListener('visibilitychange', update);
            stopRef.current();
        };
    }, [active, live]);

    const pause = useCallback(() => setLive(false), []);
    const resume = useCallback(() => setLive(true), []);

    return { live, pause, resume };
}
