import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { useTablePreferences } from '@/hooks/use-table-preferences';
import { tablePreferenceKey } from '@/lib/table-preferences';

type StoredPreferences = {
    density?: string;
    columnVisibility?: Record<string, boolean>;
    stickyHeader?: boolean;
    views?: unknown[];
    version?: number;
};

function readStored(tableId: string): StoredPreferences | null {
    const raw = window.localStorage.getItem(tablePreferenceKey(tableId));

    return raw ? (JSON.parse(raw) as StoredPreferences) : null;
}

beforeEach(() => {
    window.localStorage.clear();
});

describe('useTablePreferences', () => {
    it('hydrates stored preferences', () => {
        window.localStorage.setItem(
            tablePreferenceKey('users'),
            JSON.stringify({
                version: 1,
                density: 'compact',
                columnVisibility: { email: false },
                stickyHeader: true,
                views: [],
            }),
        );

        const { result } = renderHook(() => useTablePreferences('users'));

        expect(result.current.density).toBe('compact');
        expect(result.current.stickyHeader).toBe(true);
        expect(result.current.columnVisibility).toEqual({ email: false });
    });

    it('persists density and sticky-header changes', () => {
        const { result } = renderHook(() => useTablePreferences('users'));

        act(() => result.current.setDensity('compact'));
        act(() => result.current.setStickyHeader(true));

        const stored = readStored('users');

        expect(stored?.density).toBe('compact');
        expect(stored?.stickyHeader).toBe(true);
    });

    it('toggles column visibility and resets preferences', () => {
        const { result } = renderHook(() => useTablePreferences('users'));

        act(() => result.current.toggleColumn('email', false));
        expect(readStored('users')?.columnVisibility).toEqual({
            email: false,
        });

        act(() => result.current.resetPreferences());
        expect(readStored('users')?.columnVisibility).toEqual({});
    });

    it('creates, renames and deletes saved views', () => {
        const { result } = renderHook(() => useTablePreferences('users'));

        let viewId = '';

        act(() => {
            viewId = result.current.saveView('Active users', {
                status: 'active',
            }).id;
        });

        expect(result.current.views).toHaveLength(1);
        expect(result.current.views[0]?.name).toBe('Active users');
        expect(readStored('users')?.views).toHaveLength(1);

        act(() => result.current.renameView(viewId, 'Only active'));
        expect(result.current.views[0]?.name).toBe('Only active');

        act(() => result.current.deleteView(viewId));
        expect(result.current.views).toHaveLength(0);
        expect(readStored('users')?.views).toHaveLength(0);
    });
});
