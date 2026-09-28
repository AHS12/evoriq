import type { ReactElement } from 'react';
import { BarChart3 } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { ChartSkeleton } from '@/components/feedback/skeleton-chart';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import type { ChartDatum } from '@/components/charts/types';

type Props = {
    config: ChartConfig;
    data: ChartDatum[];
    isLoading?: boolean;
    emptyTitle: string;
    emptyDescription?: string;
    ariaLabel: string;
    className?: string;
    children: ReactElement;
};

/**
 * Shared chart surface: renders the themed container when there is data, a
 * skeleton while loading and an empty state when there is none. Keeps every
 * chart wrapper's loading/empty behaviour identical.
 */
export function ChartFrame({
    config,
    data,
    isLoading = false,
    emptyTitle,
    emptyDescription,
    ariaLabel,
    className,
    children,
}: Props) {
    if (isLoading) {
        return <ChartSkeleton className={className} />;
    }

    if (data.length === 0) {
        return (
            <EmptyState
                icon={BarChart3}
                title={emptyTitle}
                description={emptyDescription}
                className={className}
            />
        );
    }

    return (
        <ChartContainer
            config={config}
            role="img"
            aria-label={ariaLabel}
            className={className}
        >
            {children}
        </ChartContainer>
    );
}
