import { describe, expect, it, vi } from 'vitest';
import { LineChart } from '@/components/charts/line-chart';
import { render, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

const series = [{ key: 'hours', label: 'Hours' }];
const data = [{ month: 'Sep', hours: 12 }];

describe('LineChart', () => {
    it('shows the empty state when there is no data', () => {
        render(<LineChart data={[]} series={series} xKey="month" />);

        expect(screen.getByText('No data yet')).toBeInTheDocument();
    });

    it('shows the skeleton while loading', () => {
        render(
            <LineChart data={data} series={series} xKey="month" isLoading />,
        );

        expect(
            document.querySelector('[data-slot="chart-skeleton"]'),
        ).toBeInTheDocument();
    });

    it('renders the chart surface when data is present', () => {
        // Recharts warns when its container measures 0×0 in jsdom; the surface
        // (role/label) still renders, so silence the jsdom-only noise.
        vi.spyOn(console, 'warn').mockImplementation(() => {});

        render(
            <LineChart
                data={data}
                series={series}
                xKey="month"
                ariaLabel="Hours tracked"
            />,
        );

        expect(
            screen.getByRole('img', { name: 'Hours tracked' }),
        ).toBeInTheDocument();
    });
});
