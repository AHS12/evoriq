import { usePoll } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import { useNotifications } from '@/hooks/use-notifications';
import type { NotificationItem, NotificationPreferences } from '@/types';

/**
 * How often the shared `notifications` prop (and the feed) is refreshed.
 */
const POLL_INTERVAL = 15000;

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
        toast(item.title, { description: item.body ?? undefined });

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
 * announced as desktop notifications. When a new notification id appears, a
 * toast (and optional sound / desktop notification) is shown.
 */
export function useNotificationPoll(): void {
    const { recent, preferences } = useNotifications();
    const lastIdRef = useRef<number | null>(null);
    const initialisedRef = useRef(false);

    // keepAlive disables Inertia's hidden-tab throttle so new notifications
    // are still detected in the background and can fire desktop notifications.
    usePoll(
        POLL_INTERVAL,
        { only: ['notifications', 'feed', 'activeJobs'] },
        {
            keepAlive: true,
        },
    );

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
}
