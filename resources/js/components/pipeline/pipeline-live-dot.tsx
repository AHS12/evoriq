import { cn } from '@/lib/utils';
import type { PipelineTone } from '@/types';

type Props = {
    tone: PipelineTone;
    /** Whether the dot pulses (active work). Static otherwise. */
    active?: boolean;
    className?: string;
};

const TONES: Record<PipelineTone, string> = {
    error: 'bg-destructive',
    warning: 'bg-amber-500',
    info: 'bg-sky-500',
    success: 'bg-emerald-500',
};

/**
 * PIPE-08 — the decorative toned dot for the global pipeline indicator. The
 * count text next to it carries the meaning for assistive tech; the pulse is
 * suppressed under `prefers-reduced-motion`.
 */
export function PipelineLiveDot({ tone, active = true, className }: Props) {
    return (
        <span
            aria-hidden
            className={cn(
                'size-2 shrink-0 rounded-full',
                TONES[tone],
                active && 'motion-safe:animate-pulse',
                className,
            )}
        />
    );
}
