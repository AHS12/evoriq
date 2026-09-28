import { usePoll } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useNotifications } from '@/hooks/use-notifications';
import { toast } from '@/lib/toast';
import type { NotificationItem, NotificationPreferences } from '@/types';

/**
 * How often the shared `notifications` prop (and the feed) is refreshed.
 */
const POLL_INTERVAL = 15000;

/**
 * Whether the notification poll is running. Persisted so a user who pauses
 * live updates keeps them paused across reloads.
 */
const LIVE_STORAGE_KEY = 'evoriq.notifications.live';

export type NotificationPoll = {
    /** Whether the background poll is running. */
    live: boolean;
    pause: () => void;
    resume: () => void;
};

function readLivePreference(): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    try {
        return window.localStorage.getItem(LIVE_STORAGE_KEY) !== 'paused';
    } catch {
        return true;
    }
}

function writeLivePreference(live: boolean): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(LIVE_STORAGE_KEY, live ? 'live' : 'paused');
    } catch {
        // Ignore storage failures (private mode, quota).
    }
}

function playChime(): void {
    type AudioCtor = typeof AudioContext;

    const scope = globalThis as typeof globalThis & {
        webkitAudioContext?: AudioCtor;
    };
    const Ctor = scope.AudioContext ?? scope.webkitAudioContext;

    if (!Ctor) {
        return;
    }

    try {
        const context = new Ctor();
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gain.gain.value = 0.04;
        oscillator.start();
        oscillator.stop(context.currentTime + 0.15);
    } catch {
        // Ignore audio failures (autoplay policies, unsupported contexts).
    }
}

function showDesktopNotification(item: NotificationItem): void {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    try {
        new Notification(item.title, { body: item.body ?? undefined });
    } catch {
        // Ignore desktop notification failures.
    }
}

function announce(
    item: NotificationItem,
    preferences: NotificationPreferences,
): void {
    if (preferences.muted_types.includes(item.type)) {
        return;
    }

    if (preferences.inapp) {
        toast.info(item.title, {
            description: item.body ?? undefined,
            id: `notification-${item.id}`,
        });

        if (preferences.sound) {
            playChime();
        }
    }

    if (preferences.desktop) {
        showDesktopNotification(item);
    }
}

/**
 * Keeps the notification UI fresh by polling the shared `notifications` prop
 * (and the feed when present). Data still flows through Inertia props; polling
 * only triggers a partial reload.
 *
 * Polling continues while the tab is hidden so new notifications can still be
 * announced as desktop notifications. The caller can pause/resume it (persisted
 * across reloads), which is surfaced as the live indicator on the bell.
 */
export function useNotificationPoll(): NotificationPoll {
    const { recent, preferences } = useNotifications();
    const [live, setLive] = useState(readLivePreference);
    const { start, stop } = usePoll(
        POLL_INTERVAL,
        { only: ['notifications', 'feed', 'activeJobs'] },
        {
            keepAlive: true,
        },
    );
    const lastIdRef = useRef<number | null>(null);
    const initialisedRef = useRef(false);

    useEffect(() => {
        if (live) {
            start();
        } else {
            stop();
        }
        // `start`/`stop` are stable; only the intent changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [live]);

    useEffect(() => {
        const latest = recent[0]?.id ?? null;

        if (!initialisedRef.current) {
            initialisedRef.current = true;
            lastIdRef.current = latest;

            return;
        }

        const previous = lastIdRef.current;
        lastIdRef.current = latest;

        if (latest === null || previous === null || latest <= previous) {
            return;
        }

        recent
            .filter((item) => item.id > previous)
            .reverse()
            .forEach((item) => announce(item, preferences));
    }, [recent, preferences]);

    const pause = useCallback(() => {
        writeLivePreference(false);
        setLive(false);
    }, []);

    const resume = useCallback(() => {
        writeLivePreference(true);
        setLive(true);
    }, []);

    return { live, pause, resume };
}
