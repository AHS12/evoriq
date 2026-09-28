import { describe, expect, it } from 'vitest';
import { ChartFrame } from '@/components/charts/chart-frame';
import { render, screen } from '@/test/render';

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
});
