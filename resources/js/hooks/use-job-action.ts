import { router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { toast } from '@/lib/toast';

type JobActionOptions = {
    method?: 'post' | 'delete';
    data?: RequestPayload;
    errorMessage?: string;
};

/**
 * PIPE-07 — runs a job recovery action while tracking which action is in flight,
 * so its control can disable and show an inline pending state until the poll
 * confirms the new status.
 */
export function useJobAction(): {
    pending: string | null;
    run: (key: string, url: string, options?: JobActionOptions) => void;
} {
    const { t } = useTranslation();
    const [pending, setPending] = useState<string | null>(null);

    const run = (
        key: string,
        url: string,
        options: JobActionOptions = {},
    ): void => {
        setPending(key);

        const callbacks = {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setPending(null),
            onError: () =>
                toast.error(
                    options.errorMessage ??
                        t('The action could not be completed.'),
                ),
        };

        if (options.method === 'delete') {
            router.delete(url, callbacks);
        } else {
            router.post(url, options.data ?? {}, callbacks);
        }
    };

    return { pending, run };
}
