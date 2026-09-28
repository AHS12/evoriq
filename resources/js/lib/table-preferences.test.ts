import { describe, expect, it } from 'vitest';
import {
    clearTablePreferences,
    createSavedView,
    defaultTablePreferences,
    readTablePreferences,
    tablePreferenceKey,
    writeTablePreferences,
    TABLE_PREFERENCE_VERSION,
    type PreferenceStorage,
} from '@/lib/table-preferences';

function memoryStorage(): PreferenceStorage {
    const dump: Record<string, string> = {};

    return {
        getItem: (key) => (key in dump ? dump[key] : null),
        setItem: (key, value) => {
            dump[key] = value;
        },
        removeItem: (key) => {
            delete dump[key];
        },
    };
}

describe('table preferences store', () => {
    it('returns defaults with no storage or no value', () => {
        expect(readTablePreferences('users', null)).toEqual(
            defaultTablePreferences(),
        );
        expect(readTablePreferences('users', memoryStorage())).toEqual(
            defaultTablePreferences(),
        );
    });

    it('round-trips preferences and ignores invalid table ids', () => {
        const storage = memoryStorage();
        const preferences = {
            density: 'compact' as const,
            columnVisibility: { email: false },
            stickyHeader: true,
            views: [],
        };

        writeTablePreferences('users', preferences, storage);
        expect(readTablePreferences('users', storage)).toEqual(preferences);

        writeTablePreferences('bad id!', preferences, storage);
        expect(readTablePreferences('bad id!', storage)).toEqual(
            defaultTablePreferences(),
        );
    });

    it('isolates preferences per table id', () => {
        const storage = memoryStorage();

        writeTablePreferences(
            'users',
            { ...defaultTablePreferences(), density: 'compact' },
            storage,
        );

        expect(readTablePreferences('roles', storage).density).toBe(
            'comfortable',
        );
    });

    it('discards a mismatched version', () => {
        const storage = memoryStorage();
        storage.setItem(
            tablePreferenceKey('users'),
            JSON.stringify({ version: 99, density: 'compact' }),
        );

        expect(readTablePreferences('users', storage).density).toBe(
            'comfortable',
        );
    });

    it('discards malformed JSON', () => {
        const storage = memoryStorage();
        storage.setItem(tablePreferenceKey('users'), '{not json');

        expect(readTablePreferences('users', storage)).toEqual(
            defaultTablePreferences(),
        );
    });

    it('clears stored preferences', () => {
        const storage = memoryStorage();
        writeTablePreferences(
            'users',
            { ...defaultTablePreferences(), stickyHeader: true },
            storage,
        );

        clearTablePreferences('users', storage);
        expect(readTablePreferences('users', storage).stickyHeader).toBe(false);
    });

    it('parses saved views and drops malformed ones', () => {
        const storage = memoryStorage();
        storage.setItem(
            tablePreferenceKey('users'),
            JSON.stringify({
                version: TABLE_PREFERENCE_VERSION,
                density: 'comfortable',
                columnVisibility: {},
                stickyHeader: false,
                views: [
                    {
                        id: 'v1',
                        name: 'Active',
                        filters: { status: 'active' },
                        createdAt: '2026-01-01T00:00:00.000Z',
                    },
                    { id: 'broken' },
                ],
            }),
        );

        const views = readTablePreferences('users', storage).views;

        expect(views).toHaveLength(1);
        expect(views[0]?.name).toBe('Active');
    });
});

describe('createSavedView', () => {
    it('trims the name and drops empty/undefined filters', () => {
        const view = createSavedView(
            '  My view  ',
            {
                search: 'ada',
                role: null,
                status: undefined,
                page: 1,
                empty: '',
            },
            { id: 'fixed', createdAt: '2026-01-01T00:00:00.000Z' },
        );

        expect(view.name).toBe('My view');
        expect(view.filters).toEqual({ search: 'ada', page: 1 });
        expect(view.id).toBe('fixed');
        expect(view.createdAt).toBe('2026-01-01T00:00:00.000Z');
    });
});
