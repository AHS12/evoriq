import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useDataTableFilters } from '@/hooks/use-data-table-filters';

type GetOptions = {
    only?: string[];
    onFinish?: () => void;
};

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/react', () => ({ router: { get } }));

function lastCall(): {
    url: string;
    query: Record<string, string>;
    options: GetOptions;
} {
    const call = get.mock.calls.at(-1);

    if (!call) {
        throw new Error('router.get was not called');
    }

    return { url: call[0], query: call[1], options: call[2] ?? {} };
}

beforeEach(() => {
    get.mockClear();
});

describe('useDataTableFilters', () => {
    it('tracks loading only for visits it initiates', () => {
        const { result } = renderHook(() =>
            useDataTableFilters('/users', { search: '' }),
        );

        expect(result.current.isLoading).toBe(false);

        act(() => result.current.apply({ search: 'ada' }, true));

        expect(result.current.isLoading).toBe(true);

        const { url, query } = lastCall();

        expect(url).toBe('/users');
        expect(query).toEqual({ search: 'ada' });

        act(() => lastCall().options.onFinish?.());

        expect(result.current.isLoading).toBe(false);
    });

    it('replace sends exactly the given filters', () => {
        const { result } = renderHook(() =>
            useDataTableFilters('/users', { search: 'old', role: 'admin' }),
        );

        act(() => result.current.replace({ status: 'active' }));

        expect(lastCall().query).toEqual({ status: 'active' });
    });

    it('passes the only prop through to the visit', () => {
        const { result } = renderHook(() =>
            useDataTableFilters('/activity', {}, { only: ['jobs', 'stats'] }),
        );

        act(() => result.current.apply({ page: 2 }, true));

        expect(lastCall().options.only).toEqual(['jobs', 'stats']);
    });
});
