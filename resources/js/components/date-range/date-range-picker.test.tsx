import { describe, expect, it, vi } from 'vitest';
import { DateRangePicker } from '@/components/date-range/date-range-picker';
import {
    buildDateRange,
    type ComparisonRange,
    type DateRange,
} from '@/lib/date-range';
import { renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

const value = buildDateRange('this-month', { now: new Date(2026, 8, 29) });

function requireEl(selector: string): Element {
    const element = document.querySelector(selector);

    if (!element) {
        throw new Error(`Expected an element for ${selector}`);
    }

    return element;
}

function dayButton(date: Date): Element {
    return requireEl(`button[data-day="${date.toLocaleDateString()}"]`);
}

async function openPicker(user: ReturnType<typeof renderWithUser>['user']) {
    await user.click(screen.getByRole('button', { name: /Date range:/ }));
}

describe('DateRangePicker', () => {
    it('applies All time from the footer action', async () => {
        const onApply =
            vi.fn<(range: DateRange, comparison: ComparisonRange) => void>();
        const { user } = renderWithUser(
            <DateRangePicker value={value} onApply={onApply} />,
        );

        await openPicker(user);
        await user.click(requireEl('[data-test="date-range-all-time"]'));
        await user.click(screen.getByRole('button', { name: 'Apply' }));

        expect(onApply).toHaveBeenCalledOnce();
        expect(onApply.mock.calls[0]?.[0]).toMatchObject({
            preset: 'all-time',
        });
    });

    it('disables Apply after Reset', async () => {
        const onApply = vi.fn();
        const { user } = renderWithUser(
            <DateRangePicker value={value} onApply={onApply} />,
        );

        await openPicker(user);
        await user.click(requireEl('[data-test="date-range-reset"]'));

        expect(screen.getByRole('button', { name: 'Apply' })).toBeDisabled();
        expect(onApply).not.toHaveBeenCalled();
    });

    it('renders a two-month grid without duplicated outside days', async () => {
        const { user } = renderWithUser(
            <DateRangePicker value={value} onApply={vi.fn()} />,
        );

        await openPicker(user);

        expect(document.querySelectorAll('.rdp-month')).toHaveLength(2);

        const days = Array.from(
            document.querySelectorAll('button[data-day]'),
        ).map((element) => element.getAttribute('data-day'));

        expect(new Set(days).size).toBe(days.length);
    });

    it('quick-resets to all time from the trigger cross button', async () => {
        const onApply = vi.fn();
        const { user } = renderWithUser(
            <DateRangePicker value={value} onApply={onApply} />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Reset to all time' }),
        );

        expect(onApply).toHaveBeenCalledOnce();
        expect(onApply.mock.calls[0]?.[0]).toMatchObject({
            preset: 'all-time',
        });
        expect(onApply.mock.calls[0]?.[1]).toMatchObject({ mode: 'none' });
    });

    it('hides the cross button when already on all time', () => {
        renderWithUser(
            <DateRangePicker
                value={buildDateRange('all-time')}
                onApply={vi.fn()}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Reset to all time' }),
        ).not.toBeInTheDocument();
    });

    it('completes a range across two months', async () => {
        const onApply =
            vi.fn<(range: DateRange, comparison: ComparisonRange) => void>();
        const from = new Date(2026, 8, 16);
        const { user } = renderWithUser(
            <DateRangePicker
                value={{
                    preset: 'custom',
                    start: from,
                    end: null,
                    granularity: 'month',
                }}
                max={new Date(2026, 11, 31)}
                onApply={onApply}
            />,
        );

        await openPicker(user);
        await user.click(dayButton(new Date(2026, 9, 15)));
        await user.click(screen.getByRole('button', { name: 'Apply' }));

        expect(onApply).toHaveBeenCalledOnce();
        const range = onApply.mock.calls[0]?.[0];

        expect(range?.preset).toBe('custom');
        expect(range?.start?.getTime()).toBe(from.getTime());
        expect(range?.end?.getTime()).toBe(new Date(2026, 9, 15).getTime());
    });
});
