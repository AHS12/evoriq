import { describe, expect, it, vi } from 'vitest';
import { ChartFrame } from '@/components/charts/chart-frame';
import { render, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

const config = { hours: { label: 'Hours', color: 'var(--chart-1)' } };

describe('ChartFrame', () => {
    it('renders the skeleton while loading', () => {
        render(
            <ChartFrame
                config={config}
                data={[]}
                isLoading
                emptyTitle="No data"
                ariaLabel="Hours"
            >
                <svg />
            </ChartFrame>,
        );

        expect(
            document.querySelector('[data-slot="chart-skeleton"]'),
        ).toBeInTheDocument();
    });

    it('renders the empty state when there is no data', () => {
        render(
            <ChartFrame
                config={config}
                data={[]}
                emptyTitle="No data"
                emptyDescription="Import something first"
                ariaLabel="Hours"
            >
                <svg />
            </ChartFrame>,
        );

        expect(screen.getByText('No data')).toBeInTheDocument();
        expect(screen.getByText('Import something first')).toBeInTheDocument();
    });

    it('renders the error state when the load failed', () => {
        render(
            <ChartFrame
                config={config}
                data={[]}
                error={{ title: 'Could not load hours' }}
                onRetry={() => {}}
                emptyTitle="No data"
                ariaLabel="Hours"
            >
                <svg />
            </ChartFrame>,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Could not load hours',
        );
        expect(
            screen.getByRole('button', { name: 'Retry' }),
        ).toBeInTheDocument();
    });
});
