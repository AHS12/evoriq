import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

export type TableFilterValue = string | number | boolean | null | undefined;
export type TableFilters = Record<string, TableFilterValue>;

type Options = {
    only?: string[];
    debounce?: number;
    preserveScroll?: boolean;
};

type RouterEvent = CustomEvent<{
    visit: { url: string | URL; method: string };
}>;

export function useDataTableFilters(
    url: string,
    current: TableFilters,
    options: Options = {},
) {
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const isTableVisit = (event: RouterEvent) => {
            const { visit } = event.detail;

            return (
                visit.method.toLowerCase() === 'get' &&
                new URL(visit.url, window.location.origin).pathname ===
                    window.location.pathname
            );
        };

        const offStart = router.on('start', (event) => {
            if (isTableVisit(event)) {
                setIsLoading(true);
            }
        });
        const offFinish = router.on('finish', (event) => {
            if (isTableVisit(event)) {
                setIsLoading(false);
            }
        });

        return () => {
            offStart();
            offFinish();
        };
    }, []);

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

    return { apply, isLoading };
}
