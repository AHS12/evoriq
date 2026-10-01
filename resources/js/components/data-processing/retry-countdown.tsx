import { useEffect, useState } from 'react';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    /** ISO timestamp of the next automatic retry. */
    at: string | null;
    attempt: number;
    max: number;
};

function remainingSeconds(at: string): number {
    return Math.max(
        0,
        Math.round((new Date(at).getTime() - Date.now()) / 1000),
    );
}

/**
 * PIPE-07 — a live, second-by-second countdown to the next automatic retry.
 * The ticking value is hidden from assistive tech; only the "retrying now"
 * transition is announced.
 */
export function RetryCountdown({ at, attempt, max }: Props) {
    const { t } = useTranslation();
    const [remaining, setRemaining] = useState(() =>
        at ? remainingSeconds(at) : 0,
    );

    useEffect(() => {
        if (!at) {
            return;
        }

        setRemaining(remainingSeconds(at));

        const id = window.setInterval(() => {
            const next = remainingSeconds(at);
            setRemaining(next);

            if (next <= 0) {
                window.clearInterval(id);
            }
        }, 1000);

        return () => window.clearInterval(id);
    }, [at]);

    if (!at) {
        return null;
    }

    const attemptLabel =
        max > 1 ? t('Attempt :current/:max', { current: attempt, max }) : '';
    const ticking =
        remaining <= 0
            ? t('Retrying now')
            : t('Retrying in :time', {
                  time:
                      remaining < 60
                          ? `${remaining}s`
                          : `${Math.ceil(remaining / 60)}m`,
              });

    return (
        <span className="inline-flex items-center gap-1">
            <span role="status" className="sr-only">
                {remaining <= 0 ? t('Retrying now') : ''}
            </span>
            <span aria-hidden>
                {ticking}
                {attemptLabel ? ` · ${attemptLabel}` : ''}
            </span>
        </span>
    );
}
