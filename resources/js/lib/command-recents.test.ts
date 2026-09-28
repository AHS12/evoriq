import { describe, expect, it } from 'vitest';
import {
    clearCommandRecents,
    COMMAND_RECENTS_LIMIT,
    pushCommandRecent,
    readCommandRecents,
    type RecentsStorage,
} from '@/lib/command-recents';

function memoryStorage(): RecentsStorage {
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

describe('command recents', () => {
    it('returns an empty list without storage', () => {
        expect(readCommandRecents(null)).toEqual([]);
    });

    it('pushes newest first and de-duplicates', () => {
        const storage = memoryStorage();

        pushCommandRecent('nav:/users', {
            storage,
            at: '2026-01-01T00:00:00Z',
        });
        pushCommandRecent('nav:/files', {
            storage,
            at: '2026-01-02T00:00:00Z',
        });
        pushCommandRecent('nav:/users', {
            storage,
            at: '2026-01-03T00:00:00Z',
        });

        expect(readCommandRecents(storage).map((recent) => recent.id)).toEqual([
            'nav:/users',
            'nav:/files',
        ]);
    });

    it('caps the list at the configured limit', () => {
        const storage = memoryStorage();

        for (let index = 0; index < COMMAND_RECENTS_LIMIT + 3; index += 1) {
            pushCommandRecent(`id:${index}`, { storage });
        }

        expect(readCommandRecents(storage)).toHaveLength(COMMAND_RECENTS_LIMIT);
    });

    it('discards malformed storage', () => {
        const storage = memoryStorage();
        storage.setItem('evoriq.command.recent.v1', '{not json');

        expect(readCommandRecents(storage)).toEqual([]);
    });

    it('clears the list', () => {
        const storage = memoryStorage();
        pushCommandRecent('nav:/users', { storage });

        clearCommandRecents(storage);

        expect(readCommandRecents(storage)).toEqual([]);
    });
});
