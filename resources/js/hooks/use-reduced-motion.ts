import { useEffect, useState } from 'react';

const QUERY = '(prefers-reduced-motion: reduce)';

/**
 * Whether the user has asked for reduced motion. Charts use it to disable
 * entrance animation (FND-01 also strips CSS animation globally).
 */
export function useReducedMotion(): boolean {
    const [reduced, setReduced] = useState<boolean>(() =>
        typeof window === 'undefined'
            ? false
            : window.matchMedia(QUERY).matches,
    );

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const media = window.matchMedia(QUERY);
        const onChange = (event: MediaQueryListEvent): void =>
            setReduced(event.matches);

        media.addEventListener('change', onChange);

        return () => media.removeEventListener('change', onChange);
    }, []);

    return reduced;
}
