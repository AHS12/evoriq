import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

const BAR_HEIGHTS = [40, 65, 50, 80, 55, 70, 45, 90, 60, 75, 52, 68];

/**
 * Chart-shaped skeleton (columns of varying height) for data-viz surfaces.
 */
export function ChartSkeleton({ className }: Props) {
    return (
        <div
            data-slot="chart-skeleton"
            aria-hidden
            className={cn(
                'flex h-56 items-end gap-2 rounded-xl border bg-card p-4',
                className,
            )}
        >
            {BAR_HEIGHTS.map((height, index) => (
                <Skeleton
                    key={index}
                    className="flex-1 rounded-sm"
                    style={{ height: `${height}%` }}
                />
            ))}
        </div>
    );
}
