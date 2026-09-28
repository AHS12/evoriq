import { useState } from 'react';

/**
 * PIPE-06 — keeps a tab selection in a URL query param (`?tab=issues`) so run
 * inspector tabs are shareable. Client-only: it rewrites history in place
 * rather than triggering an Inertia visit.
 */
export function useUrlTab<T extends string>(
    param: string,
    fallback: T,
    valid: readonly T[],
): [T, (tab: T) => void] {
    const read = (): T => {
        if (typeof window === 'undefined') {
            return fallback;
        }

        const value = new URLSearchParams(window.location.search).get(param);

        return value !== null && (valid as readonly string[]).includes(value)
            ? (value as T)
            : fallback;
    };

    const [tab, setTabState] = useState<T>(read);

    const setTab = (next: T): void => {
        setTabState(next);

        const url = new URL(window.location.href);

        if (next === fallback) {
            url.searchParams.delete(param);
        } else {
            url.searchParams.set(param, next);
        }

        window.history.replaceState(window.history.state, '', url);
    };

    return [tab, setTab];
}
