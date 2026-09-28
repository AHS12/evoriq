import { act, cleanup, renderHook } from '@testing-library/react';
import {
    afterEach,
    beforeAll,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { useLivePoll } from '@/hooks/use-live-poll';
import { resetLivePollRegistry } from '@/hooks/use-live-poll-registry';

type ReloadOptions = {
    only?: string[];
    showProgress?: boolean;
    onSuccess?: (page: { props: Record<string, unknown> }) => void;
    onFinish?: (visit: { cancelled: boolean; interrupted: boolean }) => void;
};

const { reload } = vi.hoisted(() => ({
    reload: vi.fn<(options: ReloadOptions) => void>(),
}));

vi.mock('@inertiajs/react', () => ({ router: { reload } }));

let visibility: 'visible' | 'hidden' = 'visible';

function setVisibility(state: 'visible' | 'hidden'): void {
    visibility = state;
    document.dispatchEvent(new Event('visibilitychange'));
}

function latestOptions(): ReloadOptions {
    const options = reload.mock.calls.at(-1)?.[0];

    if (!options) {
        throw new Error('router.reload was not called');
    }

    return options;
}

function finish(ok: boolean, revision = '1|2026-09-28 01:00:00'): void {
    const options = latestOptions();

    if (ok) {
        options.onSuccess?.({ props: { pipeline_revision: revision } });
    }

    options.onFinish?.({ cancelled: false, interrupted: false });
}

beforeAll(() => {
    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => visibility,
    });
});

beforeEach(() => {
    vi.useFakeTimers();
    visibility = 'visible';
    resetLivePollRegistry();
});

afterEach(() => {
    cleanup();
    resetLivePollRegistry();
    vi.useRealTimers();
});

describe('useLivePoll', () => {
    it('shares one timer and one request across subscribers with the same url', () => {
        const options = { url: '/activity', only: ['jobs'] };

        renderHook(() => useLivePoll(options));
        renderHook(() => useLivePoll(options));

        act(() => {
            vi.advanceTimersByTime(3000);
        });

        expect(reload).toHaveBeenCalledTimes(1);

        act(() => {
            finish(true);
        });

        act(() => {
            vi.advanceTimersByTime(3000);
        });

        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('backs off exponentially, caps, and resets on success', () => {
        renderHook(() =>
            useLivePoll({ url: '/x', only: ['a'], maxInterval: 20000 }),
        );

        act(() => {
            vi.advanceTimersByTime(3000);
        });
        expect(reload).toHaveBeenCalledTimes(1);

        act(() => finish(false));
        act(() => {
            vi.advanceTimersByTime(5999);
        });
        expect(reload).toHaveBeenCalledTimes(1);
        act(() => {
            vi.advanceTimersByTime(1);
        });
        expect(reload).toHaveBeenCalledTimes(2);

        act(() => finish(false));
        act(() => {
            vi.advanceTimersByTime(11999);
        });
        expect(reload).toHaveBeenCalledTimes(2);
        act(() => {
            vi.advanceTimersByTime(1);
        });
        expect(reload).toHaveBeenCalledTimes(3);

        act(() => finish(false));
        act(() => {
            vi.advanceTimersByTime(19999);
        });
        expect(reload).toHaveBeenCalledTimes(3);
        act(() => {
            vi.advanceTimersByTime(1);
        });
        expect(reload).toHaveBeenCalledTimes(4);

        act(() => finish(true));
        act(() => {
            vi.advanceTimersByTime(2999);
        });
        expect(reload).toHaveBeenCalledTimes(4);
        act(() => {
            vi.advanceTimersByTime(1);
        });
        expect(reload).toHaveBeenCalledTimes(5);
    });

    it('pauses on request and refreshes on resume', () => {
        const { result } = renderHook(() =>
            useLivePoll({ url: '/y', only: ['a'] }),
        );

        act(() => result.current.pause());

        expect(result.current.live).toBe(false);
        expect(result.current.status).toBe('paused');

        act(() => {
            vi.advanceTimersByTime(9000);
        });
        expect(reload).not.toHaveBeenCalled();

        act(() => result.current.resume());

        expect(result.current.live).toBe(true);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('does not poll when idle polling is disabled', () => {
        renderHook(() =>
            useLivePoll({
                url: '/z',
                only: ['a'],
                enabled: false,
                idleInterval: 0,
            }),
        );

        act(() => {
            vi.advanceTimersByTime(60000);
        });

        expect(reload).not.toHaveBeenCalled();
    });

    it('stops polling while hidden and refreshes on focus', () => {
        renderHook(() => useLivePoll({ url: '/h', only: ['a'] }));

        act(() => {
            vi.advanceTimersByTime(3000);
        });
        expect(reload).toHaveBeenCalledTimes(1);

        act(() => finish(true));

        act(() => setVisibility('hidden'));
        act(() => {
            vi.advanceTimersByTime(30000);
        });
        expect(reload).toHaveBeenCalledTimes(1);

        act(() => setVisibility('visible'));
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('only stamps lastRefreshedAt when the revision changes', () => {
        const { result } = renderHook(() =>
            useLivePoll({ url: '/r', only: ['a'] }),
        );

        act(() => {
            vi.advanceTimersByTime(3000);
        });
        act(() => finish(true, 'rev-1'));

        const first = result.current.lastRefreshedAt;
        expect(first).toBeTypeOf('number');

        act(() => {
            vi.advanceTimersByTime(3000);
        });
        act(() => finish(true, 'rev-1'));
        expect(result.current.lastRefreshedAt).toBe(first);

        act(() => {
            vi.advanceTimersByTime(3000);
        });
        act(() => finish(true, 'rev-2'));
        expect(result.current.lastRefreshedAt).toBeGreaterThan(first ?? 0);
    });
});
