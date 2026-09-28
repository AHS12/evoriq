import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    /** Number of placeholder entries to render. */
    rows?: number;
    className?: string;
};

/**
 * PIPE-04 — a vertical loading placeholder that mirrors the timeline layout
 * (time gutter, marker, content blocks).
 */
export function TimelineSkeleton({ rows = 4, className }: Props) {
    return (
        <div
            data-slot="timeline-skeleton"
            aria-hidden
            className={cn('space-y-4', className)}
        >
            {Array.from({ length: rows }).map((_, index) => (
                <div key={index} className="flex gap-3">
                    <Skeleton className="h-4 w-16 shrink-0" />
                    <div className="relative flex w-6 shrink-0 justify-center">
                        <Skeleton className="size-6 rounded-full" />
                    </div>
                    <div className="min-w-0 flex-1 space-y-2">
                        <Skeleton className="h-4 w-1/3" />
                        <Skeleton className="h-3 w-2/3" />
                    </div>
                </div>
            ))}
        </div>
    );
}
