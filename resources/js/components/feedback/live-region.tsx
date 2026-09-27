import { cn } from '@/lib/utils';

type Props = {
    /** The current announcement. An empty value renders nothing. */
    message?: string;
    /** Whether the underlying work is still running. */
    busy?: boolean;
    className?: string;
};

/**
 * Polite live region for long-running updates. Announce **stage changes and
 * completion only** — never every progress tick — to avoid flooding screen
 * readers. Visually hidden by default.
 */
export function LiveRegion({ message, busy = false, className }: Props) {
    return (
        <div
            role="status"
            aria-live="polite"
            aria-busy={busy || undefined}
            className={cn('sr-only', className)}
        >
            {message}
        </div>
    );
}
