import type { ComparisonMode, DateRangePresetKey } from '@/lib/date-range';

export type PresetGroup = 'relative' | 'calendar' | 'history';

export type PeriodPreset = {
    key: DateRangePresetKey;
    /** English label — doubles as the translation key. */
    labelKey: string;
    group: PresetGroup;
};

export const PERIOD_PRESET_GROUPS: { group: PresetGroup; labelKey: string }[] =
    [
        { group: 'relative', labelKey: 'Relative' },
        { group: 'calendar', labelKey: 'Calendar' },
        { group: 'history', labelKey: 'History' },
    ];

export const PERIOD_PRESETS: PeriodPreset[] = [
    { key: 'today', labelKey: 'Today', group: 'relative' },
    { key: 'yesterday', labelKey: 'Yesterday', group: 'relative' },
    { key: 'this-week', labelKey: 'This week', group: 'relative' },
    { key: 'last-week', labelKey: 'Last week', group: 'relative' },
    { key: 'this-month', labelKey: 'This month', group: 'calendar' },
    { key: 'last-month', labelKey: 'Last month', group: 'calendar' },
    { key: 'this-quarter', labelKey: 'This quarter', group: 'calendar' },
    { key: 'last-quarter', labelKey: 'Last quarter', group: 'calendar' },
    { key: 'this-year', labelKey: 'This year', group: 'calendar' },
    { key: 'last-year', labelKey: 'Last year', group: 'calendar' },
    { key: 'last-2-years', labelKey: 'Last 2 years', group: 'history' },
    { key: 'last-5-years', labelKey: 'Last 5 years', group: 'history' },
    { key: 'all-time', labelKey: 'All time', group: 'history' },
    { key: 'custom', labelKey: 'Custom', group: 'history' },
];

export type ComparisonOption = {
    value: ComparisonMode;
    /** English label — doubles as the translation key. */
    labelKey: string;
};

export const COMPARISON_OPTIONS: ComparisonOption[] = [
    { value: 'none', labelKey: 'No comparison' },
    { value: 'previous-period', labelKey: 'Previous period' },
    { value: 'same-period-last-year', labelKey: 'Same period last year' },
    { value: 'custom', labelKey: 'Custom comparison' },
];

/** The translation key for a preset's label (falls back to `Custom`). */
export function presetLabelKey(preset: DateRangePresetKey): string {
    return (
        PERIOD_PRESETS.find((option) => option.key === preset)?.labelKey ??
        'Custom'
    );
}
