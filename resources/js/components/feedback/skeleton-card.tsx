import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    /** Number of placeholder cards to render. */
    count?: number;
    className?: string;
};

/**
 * Card-grid skeleton for KPI / metric surfaces.
 */
export function CardSkeleton({ count = 5, className }: Props) {
    return (
        <div
            data-slot="card-skeleton"
            aria-hidden
            className={cn(
                'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5',
                className,
            )}
        >
            {Array.from({ length: count }).map((_, index) => (
                <div key={index} className="rounded-xl border bg-card p-3">
                    <div className="flex items-center justify-between">
                        <Skeleton className="h-3 w-16" />
                        <Skeleton className="size-4" />
                    </div>
                    <Skeleton className="mt-2 h-7 w-10" />
                </div>
            ))}
        </div>
    );
}
