import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    type LivePollHandle,
    type LivePollOptions,
    type LivePollState,
    subscribeLivePoll,
} from '@/hooks/use-live-poll-registry';

export type {
    LivePollOptions,
    LivePollStatus,
} from '@/hooks/use-live-poll-registry';

export type UseLivePollResult = LivePollState & {
    /** Reload immediately instead of waiting for the timer. */
    refresh: () => void;
    /** User-initiated pause; outranks `enabled` and affects the shared entry. */
    pause: () => void;
    resume: () => void;
};

const INITIAL_STATE: LivePollState = {
    live: true,
    status: 'idle',
    isRefreshing: false,
    lastRefreshedAt: null,
};

/**
 * PIPE-03 — a visibility-aware, adaptive, shared polling transport.
 *
 * Subscribers with the same `url` share one timer and one in-flight request,
 * so a page, its sidebar badge and the header indicator cost one poll between
 * them. Polling pauses while the tab is hidden and resumes (with an immediate
 * refresh) on focus. Failures back off exponentially up to `maxInterval`.
 *
 * The transport is Inertia-native (`router.reload` partial reloads); the
 * registry is the seam where a broadcast implementation can be swapped in.
 */
export function useLivePoll(options: LivePollOptions): UseLivePollResult {
    const { url, only, enabled, interval, idleInterval, maxInterval } = options;

    // `only` is frequently a fresh literal; key the subscription on its value
    // so identity changes don't tear the shared entry down.
    const onlyKey = useMemo(() => JSON.stringify(only), [only]);
    const [state, setState] = useState<LivePollState>(INITIAL_STATE);
    const handleRef = useRef<LivePollHandle | null>(null);

    useEffect(() => {
        const handle = subscribeLivePoll(
            { url, only, enabled, interval, idleInterval, maxInterval },
            setState,
        );
        handleRef.current = handle;

        return () => {
            handleRef.current = null;
            handle.unsubscribe();
        };
        // `onlyKey` intentionally stands in for `only` (array identity).
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, onlyKey]);

    useEffect(() => {
        handleRef.current?.update({
            url,
            only,
            enabled,
            interval,
            idleInterval,
            maxInterval,
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [url, onlyKey, enabled, interval, idleInterval, maxInterval]);

    const refresh = useCallback(() => handleRef.current?.refresh(), []);
    const pause = useCallback(() => handleRef.current?.pause(), []);
    const resume = useCallback(() => handleRef.current?.resume(), []);

    return { ...state, refresh, pause, resume };
}
