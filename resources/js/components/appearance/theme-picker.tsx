import { Check } from 'lucide-react';
import { useAppearancePreferences } from '@/hooks/use-appearance';
import { THEME_OPTIONS } from '@/lib/appearance';
import { cn } from '@/lib/utils';

export function ThemePicker() {
    const { preferences, update } = useAppearancePreferences();

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {THEME_OPTIONS.map((theme) => {
                const active = preferences.theme === theme.value;

                return (
                    <button
                        key={theme.value}
                        type="button"
                        aria-pressed={active}
                        onClick={() => update({ theme: theme.value })}
                        className={cn(
                            'flex items-center gap-3 rounded-lg border p-3 text-left transition-colors',
                            active
                                ? 'border-primary ring-2 ring-primary/30'
                                : 'hover:border-foreground/30',
                        )}
                    >
                        <span
                            className="size-8 shrink-0 rounded-md border"
                            style={{ backgroundColor: theme.swatch }}
                        />
                        <span className="flex-1 text-sm font-medium">
                            {theme.label}
                        </span>
                        {active && <Check className="size-4 text-primary" />}
                    </button>
                );
            })}
        </div>
    );
}
