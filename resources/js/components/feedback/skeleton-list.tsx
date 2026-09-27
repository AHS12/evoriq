import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    /** Number of placeholder rows to render. */
    rows?: number;
    className?: string;
};

/**
 * Row-shaped skeleton for list surfaces (job activity, feeds, timelines).
 */
export function ListSkeleton({ rows = 5, className }: Props) {
    return (
        <div
            data-slot="list-skeleton"
            aria-hidden
            className={cn(
                'divide-y overflow-hidden rounded-xl border',
                className,
            )}
        >
            {Array.from({ length: rows }).map((_, index) => (
                <div key={index} className="flex items-center gap-3 px-4 py-3">
                    <Skeleton className="size-8 shrink-0 rounded-full" />
                    <div className="min-w-0 flex-1 space-y-2">
                        <Skeleton className="h-4 w-1/3" />
                        <Skeleton className="h-3 w-2/3" />
                    </div>
                    <Skeleton className="h-5 w-16 shrink-0" />
                </div>
            ))}
        </div>
    );
}
