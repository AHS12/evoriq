import { cn } from '@/lib/utils';

type Props = {
    /** Accessible label; when omitted the dot is decorative (`aria-hidden`). */
    label?: string;
    /** Whether the dot is pulsing. Static when `false`. */
    active?: boolean;
    className?: string;
};

/**
 * Small pulsing status dot for live surfaces (pipeline indicator, run headers).
 * The pulse is suppressed under `prefers-reduced-motion` and when inactive.
 */
export function LiveDot({ label, active = true, className }: Props) {
    return (
        <span
            className="inline-flex items-center"
            role={label ? 'status' : undefined}
        >
            <span
                aria-hidden={label ? undefined : true}
                className={cn(
                    'size-2 rounded-full',
                    active
                        ? 'bg-emerald-500 motion-safe:animate-pulse'
                        : 'bg-muted-foreground/50',
                    className,
                )}
            />
            {label ? <span className="sr-only">{label}</span> : null}
        </span>
    );
}
