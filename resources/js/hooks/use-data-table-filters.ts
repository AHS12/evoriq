import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

export type TableFilterValue = string | number | boolean | null | undefined;
export type TableFilters = Record<string, TableFilterValue>;

type Options = {
    only?: string[];
    debounce?: number;
    preserveScroll?: boolean;
};

export function useDataTableFilters(
    url: string,
    current: TableFilters,
    options: Options = {},
) {
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, []);

    const visit = useCallback(
        (filters: TableFilters) => {
            const query: Record<string, string> = {};

            Object.entries(filters).forEach(([key, value]) => {
                if (value === null || value === undefined || value === '') {
                    return;
                }

                query[key] = String(value);
            });

            router.get(url, query, {
                preserveState: true,
                preserveScroll: options.preserveScroll ?? true,
                replace: true,
                only: options.only,
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

    return { apply };
}
