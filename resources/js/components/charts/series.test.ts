import { describe, expect, it } from 'vitest';
import { buildChartConfig, seriesColor } from '@/components/charts/series';

describe('seriesColor', () => {
    it('cycles the five chart tokens', () => {
        expect(seriesColor(0)).toBe('var(--chart-1)');
        expect(seriesColor(4)).toBe('var(--chart-5)');
        expect(seriesColor(5)).toBe('var(--chart-1)');
    });
});

describe('buildChartConfig', () => {
    it('assigns tokens in order and keeps explicit colours', () => {
        const config = buildChartConfig([
            { key: 'hours', label: 'Hours' },
            { key: 'billable', label: 'Billable', color: 'var(--chart-5)' },
        ]);

        expect(config).toEqual({
            hours: { label: 'Hours', color: 'var(--chart-1)' },
            billable: { label: 'Billable', color: 'var(--chart-5)' },
        });
    });
});
