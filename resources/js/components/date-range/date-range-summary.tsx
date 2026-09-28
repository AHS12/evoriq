import { presetLabelKey } from '@/components/date-range/period-presets';
import { useTranslation } from '@/hooks/use-translation';
import {
    formatRangeLabel,
    type ComparisonRange,
    type DateRange,
} from '@/lib/date-range';
import { cn } from '@/lib/utils';

type Props = {
    range: DateRange;
    comparison?: ComparisonRange;
    className?: string;
};

/**
 * Compact trigger label for the picker, e.g. `Sep 1 – Sep 30, 2026 · vs Sep 2025`.
 */
export function DateRangeSummary({ range, comparison, className }: Props) {
    const { t } = useTranslation();
    const primary =
        formatRangeLabel(range.start, range.end) ||
        t(presetLabelKey(range.preset));
    const comparisonLabel =
        comparison && comparison.mode !== 'none'
            ? formatRangeLabel(comparison.start, comparison.end)
            : '';

    return (
        <span className={cn('truncate', className)}>
            {primary}
            {comparisonLabel ? (
                <span className="text-muted-foreground">
                    {` · ${t('vs')} ${comparisonLabel}`}
                </span>
            ) : null}
        </span>
    );
}
