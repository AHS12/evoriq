import { CalendarRange } from 'lucide-react';
import { DateRangePicker } from '@/components/date-range/date-range-picker';
import { useTranslation } from '@/hooks/use-translation';
import {
    buildDateRange,
    type DateRange,
    type DateRangePresetKey,
} from '@/lib/date-range';
import { cn } from '@/lib/utils';

type Props = {
    value: DateRange;
    onChange: (range: DateRange) => void;
    maxYears: number;
    disabled?: boolean;
};

type PresetCard = {
    preset: DateRangePresetKey;
    label: string;
    description: string;
};

const PRESET_CARDS: PresetCard[] = [
    {
        preset: 'last-year',
        label: 'Last year',
        description: 'Import the previous 12 months of time data.',
    },
    {
        preset: 'last-2-years',
        label: 'Last 2 years',
        description: 'A fuller history for trend comparisons.',
    },
    {
        preset: 'last-5-years',
        label: 'Last 5 years',
        description: 'The maximum history Evoriq imports.',
    },
    {
        preset: 'custom',
        label: 'Custom range',
        description: 'Pick any start and end date.',
    },
];

/**
 * PIPE-09 step 2 — historical presets (capped at 5 years, DEC-003) plus a
 * custom range, using the shared FND-03 picker.
 */
export function RangeStep({ value, onChange, maxYears, disabled }: Props) {
    const { t } = useTranslation();

    const max = new Date();
    const min = new Date();
    min.setFullYear(min.getFullYear() - maxYears);

    return (
        <div className="space-y-5">
            <div
                role="radiogroup"
                aria-label={t('Choose a range')}
                className="grid gap-3 sm:grid-cols-2"
            >
                {PRESET_CARDS.map((card) => {
                    const active = value.preset === card.preset;

                    return (
                        <button
                            key={card.preset}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            disabled={disabled}
                            onClick={() =>
                                onChange(
                                    buildDateRange(card.preset, { min, max }),
                                )
                            }
                            className={cn(
                                'rounded-xl border p-4 text-left transition-smooth-fast outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                                active
                                    ? 'border-primary bg-primary/5'
                                    : 'hover:border-primary/40',
                            )}
                        >
                            <span className="flex items-center gap-2 font-medium">
                                <CalendarRange
                                    aria-hidden
                                    className="size-4 text-muted-foreground"
                                />
                                {t(card.label)}
                            </span>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                {t(card.description)}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="max-w-md">
                <p className="mb-2 text-sm font-medium">{t('Custom range')}</p>
                <DateRangePicker
                    value={value}
                    onApply={(range) => onChange(range)}
                    min={min}
                    max={max}
                    disabled={disabled}
                />
            </div>
        </div>
    );
}
