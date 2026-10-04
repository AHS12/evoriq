import { usePage } from '@inertiajs/react';
import { useLivePoll } from '@/hooks/use-live-poll';
import type { ApiUsage } from '@/types';

/**
 * SYNC-17 — the active connection's API budget, shared via the `apiUsage`
 * Inertia prop and kept fresh with a slow idle poll so the reset countdown
 * stays accurate. Polling is off when there is no connection.
 */
export function useApiUsage(): {
    usage: ApiUsage | null;
    pause: () => void;
    resume: () => void;
} {
    const page = usePage();
    const raw = page.props.apiUsage as ApiUsage | null | undefined;
    const usage = raw ?? null;

    const { pause, resume } = useLivePoll({
        // A stable registry key; the transport always reloads the current page.
        url: 'api-usage',
        only: ['apiUsage'],
        enabled: false,
        idleInterval: usage === null ? 0 : 15000,
    });

    return { usage, pause, resume };
}
