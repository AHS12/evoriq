/**
 * FND-08 — recent command ids for the command palette (local-first).
 * Pure, storage-injected helpers so tests and SSR never touch `localStorage`.
 */

export type CommandRecent = {
    id: string;
    at: string;
};

export const COMMAND_RECENTS_LIMIT = 5;

const STORAGE_KEY = 'evoriq.command.recent.v1';

export type RecentsStorage = {
    getItem: (key: string) => string | null;
    setItem: (key: string, value: string) => void;
    removeItem: (key: string) => void;
};

export function resolveRecentsStorage(): RecentsStorage | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const storage = window.localStorage;
        const probe = `${STORAGE_KEY}.__probe__`;
        storage.setItem(probe, '1');
        storage.removeItem(probe);

        return storage;
    } catch {
        return null;
    }
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function parseRecents(raw: unknown): CommandRecent[] {
    if (!Array.isArray(raw)) {
        return [];
    }

    const recents: CommandRecent[] = [];

    for (const entry of raw) {
        if (!isRecord(entry)) {
            continue;
        }

        const { id, at } = entry;

        if (typeof id === 'string' && typeof at === 'string') {
            recents.push({ id, at });
        }
    }

    return recents.slice(0, COMMAND_RECENTS_LIMIT);
}

/** Read the recent command ids, newest first. Never throws. */
export function readCommandRecents(
    storage: RecentsStorage | null = resolveRecentsStorage(),
): CommandRecent[] {
    if (!storage) {
        return [];
    }

    const raw = storage.getItem(STORAGE_KEY);

    if (!raw) {
        return [];
    }

    try {
        return parseRecents(JSON.parse(raw));
    } catch {
        return [];
    }
}

/** Record a command id at the top, de-duplicated and capped. Never throws. */
export function pushCommandRecent(
    id: string,
    options: { at?: string; storage?: RecentsStorage | null } = {},
): CommandRecent[] {
    const storage =
        options.storage === undefined
            ? resolveRecentsStorage()
            : options.storage;

    const next = [
        { id, at: options.at ?? new Date().toISOString() },
        ...readCommandRecents(storage).filter((recent) => recent.id !== id),
    ].slice(0, COMMAND_RECENTS_LIMIT);

    if (storage) {
        try {
            storage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // Storage unavailable/full — recents simply do not persist.
        }
    }

    return next;
}

export function clearCommandRecents(
    storage: RecentsStorage | null = resolveRecentsStorage(),
): void {
    if (!storage) {
        return;
    }

    try {
        storage.removeItem(STORAGE_KEY);
    } catch {
        // ignore
    }
}
