import type {
    AppearanceContrast,
    AppearanceMode,
    AppearanceTheme,
} from '@/hooks/use-appearance';

export type AppearanceOption<T extends string> = {
    value: T;
    label: string;
};

export const MODE_OPTIONS: AppearanceOption<AppearanceMode>[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'System' },
];

export const THEME_OPTIONS: (AppearanceOption<AppearanceTheme> & {
    swatch: string;
})[] = [
    { value: 'default', label: 'Default', swatch: '#ffffff' },
    { value: 'dracula', label: 'Dracula', swatch: '#282a36' },
    { value: 'khaki', label: 'Khaki', swatch: '#f4efe0' },
];

export const ACCENT_PRESETS: { label: string; color: string }[] = [
    { label: 'Blue', color: '#3b82f6' },
    { label: 'Violet', color: '#8b5cf6' },
    { label: 'Emerald', color: '#10b981' },
    { label: 'Amber', color: '#f59e0b' },
    { label: 'Rose', color: '#e11d48' },
    { label: 'Slate', color: '#64748b' },
];

export const CONTRAST_OPTIONS: AppearanceOption<AppearanceContrast>[] = [
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'High' },
];
