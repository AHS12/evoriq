import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useNotificationPoll } from '@/hooks/use-notification-poll';

const { start, stop } = vi.hoisted(() => ({
    start: vi.fn(),
    stop: vi.fn(),
}));

vi.mock('@inertiajs/react', () => ({
    usePoll: () => ({ start, stop, polling: true }),
}));

vi.mock('@/hooks/use-notifications', () => ({
    useNotifications: () => ({
        unread_count: 0,
        recent: [],
        preferences: {
            inapp: true,
            sound: false,
            desktop: false,
            muted_types: [],
        },
    }),
}));

beforeEach(() => {
    window.localStorage.clear();
    start.mockClear();
    stop.mockClear();
});

describe('useNotificationPoll', () => {
    it('defaults to live and persists pause/resume', () => {
        const { result } = renderHook(() => useNotificationPoll());

        expect(result.current.live).toBe(true);

        act(() => result.current.pause());

        expect(result.current.live).toBe(false);
        expect(window.localStorage.getItem('evoriq.notifications.live')).toBe(
            'paused',
        );

        act(() => result.current.resume());

        expect(result.current.live).toBe(true);
        expect(window.localStorage.getItem('evoriq.notifications.live')).toBe(
            'live',
        );
    });

    it('honours a persisted pause on mount', () => {
        window.localStorage.setItem('evoriq.notifications.live', 'paused');

        const { result } = renderHook(() => useNotificationPoll());

        expect(result.current.live).toBe(false);
    });
});
