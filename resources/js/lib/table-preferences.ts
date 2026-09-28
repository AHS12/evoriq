/**
 * FND-07 — per-table UI preferences and saved views (local-first).
 *
 * Pure, storage-injected helpers so the hook and tests never touch the global
 * `localStorage` directly. Data is namespaced by a stable `tableId` and
 * versioned, so a future schema change can be migrated or safely discarded.
 */

export type TableDensity = 'comfortable' | 'compact';

export type TableSavedView = {
    id: string;
    name: string;
    filters: Record<string, string | number | boolean | null>;
    createdAt: string;
};

/** Input accepted when creating a view; `undefined`/`''` entries are dropped. */
export type SavedViewFilterInput = Record<
    string,
    string | number | boolean | null | undefined
>;

export type TablePreferences = {
    density: TableDensity;
    columnVisibility: Record<string, boolean>;
    stickyHeader: boolean;
    views: TableSavedView[];
};

export const TABLE_PREFERENCE_VERSION = 1;

export const TABLE_DENSITIES: TableDensity[] = ['comfortable', 'compact'];

const STORAGE_PREFIX = 'evoriq.table';
const TABLE_ID_PATTERN = /^[a-z0-9][a-z0-9-_.]*$/i;

/** Minimal storage surface used by the helpers (injectable in tests/SSR). */
export type PreferenceStorage = {
    getItem: (key: string) => string | null;
    setItem: (key: string, value: string) => void;
    removeItem: (key: string) => void;
};

type StoredTablePreferences = TablePreferences & {
    version: typeof TABLE_PREFERENCE_VERSION;
};

export function defaultTablePreferences(): TablePreferences {
    return {
        density: 'comfortable',
        columnVisibility: {},
        stickyHeader: false,
        views: [],
    };
}

export function tablePreferenceKey(tableId: string): string {
    return `${STORAGE_PREFIX}.${tableId}.v${TABLE_PREFERENCE_VERSION}`;
}

/** Resolve a usable storage, or `null` when unavailable (SSR/private mode). */
export function resolvePreferenceStorage(): PreferenceStorage | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const storage = window.localStorage;

        // Access can throw in private mode / sandboxed frames.
        const probe = `${STORAGE_PREFIX}.__probe__`;
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

function isDensity(value: unknown): value is TableDensity {
    return value === 'comfortable' || value === 'compact';
}

function parseColumnVisibility(value: unknown): Record<string, boolean> {
    if (!isRecord(value)) {
        return {};
    }

    const next: Record<string, boolean> = {};

    for (const [key, visible] of Object.entries(value)) {
        if (typeof visible === 'boolean') {
            next[key] = visible;
        }
    }

    return next;
}

function parseSavedView(value: unknown): TableSavedView | null {
    if (!isRecord(value)) {
        return null;
    }

    const { id, name, filters, createdAt } = value;

    if (
        typeof id !== 'string' ||
        typeof name !== 'string' ||
        typeof createdAt !== 'string' ||
        !isRecord(filters)
    ) {
        return null;
    }

    const parsedFilters: Record<string, string | number | boolean | null> = {};

    for (const [key, filter] of Object.entries(filters)) {
        if (
            filter === null ||
            typeof filter === 'string' ||
            typeof filter === 'number' ||
            typeof filter === 'boolean'
        ) {
            parsedFilters[key] = filter;
        }
    }

    return { id, name, filters: parsedFilters, createdAt };
}

function parseStored(raw: unknown): TablePreferences | null {
    if (!isRecord(raw) || raw.version !== TABLE_PREFERENCE_VERSION) {
        return null;
    }

    const defaults = defaultTablePreferences();

    return {
        density: isDensity(raw.density) ? raw.density : defaults.density,
        columnVisibility: parseColumnVisibility(raw.columnVisibility),
        stickyHeader:
            typeof raw.stickyHeader === 'boolean'
                ? raw.stickyHeader
                : defaults.stickyHeader,
        views: Array.isArray(raw.views)
            ? raw.views
                  .map(parseSavedView)
                  .filter((view): view is TableSavedView => view !== null)
            : defaults.views,
    };
}

/** Read and validate preferences for a table; never throws. */
export function readTablePreferences(
    tableId: string,
    storage: PreferenceStorage | null = resolvePreferenceStorage(),
): TablePreferences {
    if (!storage || !TABLE_ID_PATTERN.test(tableId)) {
        return defaultTablePreferences();
    }

    const raw = storage.getItem(tablePreferenceKey(tableId));

    if (!raw) {
        return defaultTablePreferences();
    }

    try {
        return parseStored(JSON.parse(raw)) ?? defaultTablePreferences();
    } catch {
        return defaultTablePreferences();
    }
}

/** Persist preferences for a table; never throws. */
export function writeTablePreferences(
    tableId: string,
    preferences: TablePreferences,
    storage: PreferenceStorage | null = resolvePreferenceStorage(),
): void {
    if (!storage || !TABLE_ID_PATTERN.test(tableId)) {
        return;
    }

    const payload: StoredTablePreferences = {
        version: TABLE_PREFERENCE_VERSION,
        ...preferences,
    };

    try {
        storage.setItem(tablePreferenceKey(tableId), JSON.stringify(payload));
    } catch {
        // Storage full or unavailable — preferences simply do not persist.
    }
}

export function clearTablePreferences(
    tableId: string,
    storage: PreferenceStorage | null = resolvePreferenceStorage(),
): void {
    if (!storage || !TABLE_ID_PATTERN.test(tableId)) {
        return;
    }

    try {
        storage.removeItem(tablePreferenceKey(tableId));
    } catch {
        // ignore
    }
}

function createId(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `view-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

/** Build a saved view, defaulting the id/createdAt so callers stay simple. */
export function createSavedView(
    name: string,
    filters: SavedViewFilterInput,
    options: { id?: string; createdAt?: string } = {},
): TableSavedView {
    const clean: TableSavedView['filters'] = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value !== undefined && value !== null && value !== '') {
            clean[key] = value;
        }
    }

    return {
        id: options.id ?? createId(),
        name: name.trim(),
        filters: clean,
        createdAt: options.createdAt ?? new Date().toISOString(),
    };
}
