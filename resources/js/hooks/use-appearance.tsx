import { router, usePage } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import { update as updateAppearanceRoute } from '@/routes/appearance';

export type ResolvedAppearance = 'light' | 'dark';
export type AppearanceMode = 'light' | 'dark' | 'system';
export type AppearanceTheme = 'default' | 'dracula' | 'khaki';
export type AppearanceContrast = 'normal' | 'high';

export type AppearancePreferences = {
    mode: AppearanceMode;
    theme: AppearanceTheme;
    accent: string;
    contrast: AppearanceContrast;
};

export const DEFAULT_APPEARANCE: AppearancePreferences = {
    mode: 'system',
    theme: 'default',
    accent: '#3b82f6',
    contrast: 'normal',
};

const STORAGE_KEY = 'appearance';
const COOKIE_NAME = 'appearance';
const HEX_COLOR = /^#[0-9a-f]{6}$/i;

const listeners = new Set<() => void>();
let current: AppearancePreferences = DEFAULT_APPEARANCE;

const isHexColor = (value: unknown): value is string =>
    typeof value === 'string' && HEX_COLOR.test(value);

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const isDarkMode = (mode: AppearanceMode): boolean =>
    mode === 'dark' || (mode === 'system' && prefersDark());

const accentForeground = (hex: string): string => {
    const value = hex.replace('#', '');

    if (!/^[0-9a-f]{6}$/i.test(value)) {
        return 'oklch(0.985 0 0)';
    }

    const red = parseInt(value.slice(0, 2), 16);
    const green = parseInt(value.slice(2, 4), 16);
    const blue = parseInt(value.slice(4, 6), 16);

    const luminance = (0.299 * red + 0.587 * green + 0.114 * blue) / 255;

    return luminance > 0.6 ? 'oklch(0.205 0 0)' : 'oklch(0.985 0 0)';
};

const writeCookie = (preferences: AppearancePreferences): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const value = [
        `mode=${preferences.mode}`,
        `theme=${preferences.theme}`,
        `accent=${encodeURIComponent(preferences.accent)}`,
        `contrast=${preferences.contrast}`,
    ].join('&');

    const maxAge = 365 * 24 * 60 * 60;

    document.cookie = `${COOKIE_NAME}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyTheme = (preferences: AppearancePreferences): void => {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;
    const isDark = isDarkMode(preferences.mode);

    root.classList.toggle('dark', isDark);
    root.dataset.mode = preferences.mode;
    root.dataset.theme = preferences.theme;
    root.dataset.accent = preferences.accent;
    root.dataset.contrast = preferences.contrast;
    root.style.colorScheme = isDark ? 'dark' : 'light';

    const foreground = accentForeground(preferences.accent);

    root.style.setProperty('--primary', preferences.accent);
    root.style.setProperty('--primary-foreground', foreground);
    root.style.setProperty('--ring', preferences.accent);
    root.style.setProperty('--sidebar-primary', preferences.accent);
    root.style.setProperty('--sidebar-primary-foreground', foreground);
    root.style.setProperty('--sidebar-ring', preferences.accent);
    root.style.setProperty('--chart-1', preferences.accent);
};

const readStorage = (): Partial<AppearancePreferences> => {
    if (typeof window === 'undefined') {
        return {};
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return {};
        }

        const parsed: unknown = JSON.parse(raw);

        return typeof parsed === 'object' && parsed !== null
            ? (parsed as Partial<AppearancePreferences>)
            : {};
    } catch {
        return {};
    }
};

const readInitial = (): AppearancePreferences => {
    if (typeof document === 'undefined') {
        return DEFAULT_APPEARANCE;
    }

    const dataset = document.documentElement.dataset;
    const stored = readStorage();
    const accent = dataset.accent ?? stored.accent;

    return {
        mode: (dataset.mode as AppearanceMode) ?? stored.mode ?? 'system',
        theme: (dataset.theme as AppearanceTheme) ?? stored.theme ?? 'default',
        accent: isHexColor(accent) ? accent : DEFAULT_APPEARANCE.accent,
        contrast:
            (dataset.contrast as AppearanceContrast) ??
            stored.contrast ??
            'normal',
    };
};

const persistLocal = (preferences: AppearancePreferences): void => {
    if (typeof window !== 'undefined') {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
        } catch {
            // Ignore storage failures (private mode, quota).
        }
    }

    writeCookie(preferences);
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

const mediaQuery = (): MediaQueryList | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const handleSystemThemeChange = (): void => applyTheme(current);

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    current = readInitial();
    applyTheme(current);
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function getAppearance(): AppearancePreferences {
    return current;
}

export function updateAppearance(
    partial: Partial<AppearancePreferences>,
): AppearancePreferences {
    current = { ...current, ...partial };
    persistLocal(current);
    applyTheme(current);
    notify();

    return current;
}

const PERSIST_DEBOUNCE_MS = 500;

let persistTimer: ReturnType<typeof setTimeout> | null = null;

function persistAppearance(preferences: AppearancePreferences): void {
    if (persistTimer) {
        clearTimeout(persistTimer);
    }

    persistTimer = setTimeout(() => {
        persistTimer = null;

        router.patch(
            updateAppearanceRoute.url(),
            { ...preferences, silent: true },
            {
                preserveScroll: true,
                preserveState: true,
                only: [],
            },
        );
    }, PERSIST_DEBOUNCE_MS);
}

export type UseAppearanceReturn = {
    readonly appearance: AppearanceMode;
    readonly preferences: AppearancePreferences;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (
        partial: Partial<AppearancePreferences>,
    ) => void;
};

export function useAppearance(): UseAppearanceReturn {
    const preferences = useSyncExternalStore(
        subscribe,
        () => current,
        () => DEFAULT_APPEARANCE,
    );

    const resolvedAppearance: ResolvedAppearance = isDarkMode(preferences.mode)
        ? 'dark'
        : 'light';

    return {
        appearance: preferences.mode,
        preferences,
        resolvedAppearance,
        updateAppearance,
    } as const;
}

export type UseAppearancePreferencesReturn = {
    readonly preferences: AppearancePreferences;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly update: (partial: Partial<AppearancePreferences>) => void;
};

/**
 * Appearance preferences that are applied instantly and, for authenticated
 * users, persisted to the server in the background.
 */
export function useAppearancePreferences(): UseAppearancePreferencesReturn {
    const { preferences, resolvedAppearance } = useAppearance();
    const page = usePage();

    const update = (partial: Partial<AppearancePreferences>): void => {
        const next = updateAppearance(partial);

        if (page.props.auth?.user) {
            persistAppearance(next);
        }
    };

    return { preferences, resolvedAppearance, update };
}
