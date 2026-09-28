import { CalendarIcon, ChevronDownIcon, X } from 'lucide-react';
import { useState } from 'react';
import {
    COMPARISON_OPTIONS,
    PERIOD_PRESETS,
    PERIOD_PRESET_GROUPS,
    presetLabelKey,
} from '@/components/date-range/period-presets';
import { DateRangeSummary } from '@/components/date-range/date-range-summary';
import { Calendar } from '@/components/ui/calendar';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import {
    buildDateRange,
    formatRangeLabel,
    granularityForRange,
    resolveComparison,
    type ComparisonMode,
    type ComparisonRange,
    type DateRange,
    type DateRangePresetKey,
    type ResolvedRange,
} from '@/lib/date-range';
import { cn } from '@/lib/utils';

type Props = {
    value: DateRange;
    comparison?: ComparisonRange;
    onApply: (range: DateRange, comparison: ComparisonRange) => void;
    /** Earliest selectable/available day (e.g. first synced day). */
    min?: Date;
    /** Latest selectable day (usually "today"). */
    max?: Date;
    disabled?: boolean;
    align?: 'start' | 'center' | 'end';
    className?: string;
};

const EMPTY_RANGE: ResolvedRange = { start: null, end: null };

/**
 * Shared period picker: presets, a custom day-range calendar and an optional
 * comparison. Controlled through `value`/`onApply`; URL persistence is the
 * caller's concern (see `use-date-range`).
 */
export function DateRangePicker({
    value,
    comparison,
    onApply,
    min,
    max,
    disabled = false,
    align = 'start',
    className,
}: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<DateRange>(value);
    const [compareMode, setCompareMode] = useState<ComparisonMode>(
        comparison?.mode ?? 'none',
    );
    const [customCompare, setCustomCompare] =
        useState<ResolvedRange>(EMPTY_RANGE);

    const primaryLabel =
        formatRangeLabel(value.start, value.end) ||
        t(presetLabelKey(value.preset));

    const handleOpenChange = (next: boolean): void => {
        if (next) {
            setDraft(value);
            setCompareMode(comparison?.mode ?? 'none');
            setCustomCompare({
                start: comparison?.start ?? null,
                end: comparison?.end ?? null,
            });
        }

        setOpen(next);
    };

    const selectPreset = (preset: DateRangePresetKey): void => {
        if (preset === 'custom') {
            setDraft((current) => ({ ...current, preset: 'custom' }));

            return;
        }

        setDraft(buildDateRange(preset, { min, max }));
    };

    const selectAllTime = (): void => {
        setDraft(buildDateRange('all-time', { min, max }));
    };

    const resetRange = (): void => {
        setDraft({
            preset: 'custom',
            start: null,
            end: null,
            granularity: 'month',
        });
    };

    const isAllTime = value.preset === 'all-time';
    const clearable = !disabled && !isAllTime;

    const clearToAllTime = (): void => {
        setOpen(false);
        onApply(buildDateRange('all-time', { min, max }), {
            mode: 'none',
            start: null,
            end: null,
        });
    };

    const resolvedComparison = resolveComparison(
        compareMode,
        { start: draft.start, end: draft.end },
        customCompare,
    );

    const canApply =
        draft.preset !== 'custom' ||
        (draft.start !== null && draft.end !== null);

    const apply = (): void => {
        onApply(draft, resolvedComparison);
        setOpen(false);
    };

    const disabledDays: ({ before: Date } | { after: Date })[] = [];

    if (min) {
        disabledDays.push({ before: min });
    }

    if (max) {
        disabledDays.push({ after: max });
    }

    return (
        <Popover open={open} onOpenChange={handleOpenChange}>
            <div className={cn('relative inline-flex', className)}>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-haspopup="dialog"
                        aria-label={t('Date range: :range', {
                            range: primaryLabel,
                        })}
                        className={cn(
                            'w-full justify-between gap-2 font-normal',
                            clearable && 'pr-12',
                        )}
                    >
                        <CalendarIcon className="size-4 shrink-0 text-muted-foreground" />
                        <DateRangeSummary
                            range={value}
                            comparison={comparison}
                            className="flex-1 text-left"
                        />
                        <ChevronDownIcon className="size-4 shrink-0 text-muted-foreground" />
                    </Button>
                </PopoverTrigger>
                {clearable && (
                    <button
                        type="button"
                        onClick={clearToAllTime}
                        aria-label={t('Reset to all time')}
                        title={t('Reset to all time')}
                        className="absolute top-1/2 right-8 -translate-y-1/2 rounded-sm p-0.5 text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>

            <PopoverContent
                align={align}
                className="w-auto max-w-[calc(100vw-2rem)] p-0"
            >
                <div className="flex flex-col md:flex-row">
                    <div className="scrollbar-hidden max-h-80 w-full overflow-y-auto p-2 md:w-48">
                        {PERIOD_PRESET_GROUPS.map((group) => (
                            <div key={group.group} className="mb-2">
                                <p className="px-2 py-1 text-xs font-medium text-muted-foreground">
                                    {t(group.labelKey)}
                                </p>
                                {PERIOD_PRESETS.filter(
                                    (preset) => preset.group === group.group,
                                ).map((preset) => (
                                    <button
                                        key={preset.key}
                                        type="button"
                                        onClick={() => selectPreset(preset.key)}
                                        aria-pressed={
                                            draft.preset === preset.key
                                        }
                                        className={cn(
                                            'block w-full rounded-md px-2 py-1.5 text-left text-sm transition-smooth-fast',
                                            draft.preset === preset.key
                                                ? 'bg-accent text-accent-foreground'
                                                : 'hover:bg-muted',
                                        )}
                                    >
                                        {t(preset.labelKey)}
                                    </button>
                                ))}
                            </div>
                        ))}
                    </div>

                    <div className="border-t md:border-t-0 md:border-l">
                        <div className="scrollbar-hidden overflow-x-auto">
                            <Calendar
                                mode="range"
                                numberOfMonths={2}
                                showOutsideDays={false}
                                selected={{
                                    from: draft.start ?? undefined,
                                    to: draft.end ?? undefined,
                                }}
                                defaultMonth={
                                    draft.start ?? value.start ?? undefined
                                }
                                disabled={disabledDays}
                                onSelect={(selected) =>
                                    setDraft({
                                        preset: 'custom',
                                        start: selected?.from ?? null,
                                        end: selected?.to ?? null,
                                        granularity: granularityForRange(
                                            selected?.from ?? null,
                                            selected?.to ?? null,
                                        ),
                                    })
                                }
                            />
                        </div>

                        <div className="space-y-3 border-t p-3">
                            <div className="flex items-center gap-2">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {t('Compare to')}
                                </span>
                                <Select
                                    value={compareMode}
                                    onValueChange={(next) =>
                                        setCompareMode(next as ComparisonMode)
                                    }
                                >
                                    <SelectTrigger size="sm" className="flex-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {COMPARISON_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {t(option.labelKey)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {compareMode !== 'none' && (
                                <p
                                    className="text-xs text-muted-foreground"
                                    aria-live="polite"
                                >
                                    {formatRangeLabel(
                                        resolvedComparison.start,
                                        resolvedComparison.end,
                                    ) || t('Select a range')}
                                </p>
                            )}

                            {compareMode === 'custom' && (
                                <div className="rounded-md border">
                                    <Calendar
                                        mode="range"
                                        numberOfMonths={1}
                                        showOutsideDays={false}
                                        selected={{
                                            from:
                                                customCompare.start ??
                                                undefined,
                                            to: customCompare.end ?? undefined,
                                        }}
                                        onSelect={(selected) =>
                                            setCustomCompare({
                                                start: selected?.from ?? null,
                                                end: selected?.to ?? null,
                                            })
                                        }
                                    />
                                </div>
                            )}

                            <div className="flex items-center justify-between gap-2">
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={resetRange}
                                        data-test="date-range-reset"
                                    >
                                        {t('Reset')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={selectAllTime}
                                        data-test="date-range-all-time"
                                    >
                                        {t('All time')}
                                    </Button>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setOpen(false)}
                                    >
                                        {t('Cancel')}
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={apply}
                                        disabled={!canApply}
                                    >
                                        {t('Apply')}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
