import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export type TableFilterValue = string | number | boolean | null | undefined;
export type TableFilters = Record<string, TableFilterValue>;

type Options = {
    only?: string[];
    debounce?: number;
    preserveScroll?: boolean;
};

/** Drop empty values so they never appear in the query string. */
function toQuery(filters: TableFilters): Record<string, string> {
    const query: Record<string, string> = {};

    Object.entries(filters).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return;
        }

        query[key] = String(value);
    });

    return query;
}

/**
 * URL-synced list filters for the shared table.
 *
 * `isLoading` is scoped to the visits this hook initiates. Background partial
 * reloads (the notification poll, live polls, another widget's reload) must
 * never make a list look like it is fetching, so we deliberately do **not**
 * listen to global router events.
 */
export function useDataTableFilters(
    url: string,
    current: TableFilters,
    options: Options = {},
) {
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, []);

    const visit = useCallback(
        (filters: TableFilters) => {
            if (timer.current) {
                clearTimeout(timer.current);
                timer.current = null;
            }

            setIsLoading(true);

            router.get(url, toQuery(filters), {
                preserveState: true,
                preserveScroll: options.preserveScroll ?? true,
                replace: true,
                only: options.only,
                onFinish: () => setIsLoading(false),
            });
        },
        [url, options.only, options.preserveScroll],
    );

    const apply = useCallback(
        (changes: TableFilters, immediate = false) => {
            const next = { ...current, ...changes };

            if (timer.current) {
                clearTimeout(timer.current);
            }

            if (immediate) {
                visit(next);

                return;
            }

            timer.current = setTimeout(
                () => visit(next),
                options.debounce ?? 300,
            );
        },
        [current, visit, options.debounce],
    );

    /** Replace the query entirely (saved views) instead of merging. */
    const replace = useCallback(
        (filters: TableFilters) => {
            visit(filters);
        },
        [visit],
    );

    return { apply, replace, isLoading };
}
