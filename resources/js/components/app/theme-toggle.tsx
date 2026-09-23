import type { LucideIcon } from 'lucide-react';
import { Contrast, Monitor, Moon, Sun } from 'lucide-react';
import { AccentPicker } from '@/components/appearance/accent-picker';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Switch } from '@/components/ui/switch';
import type { AppearanceMode } from '@/hooks/use-appearance';
import { useAppearancePreferences } from '@/hooks/use-appearance';
import { MODE_OPTIONS, THEME_OPTIONS } from '@/lib/appearance';
import { cn } from '@/lib/utils';

const modeIcons: Record<AppearanceMode, LucideIcon> = {
    light: Sun,
    dark: Moon,
    system: Monitor,
};

export function ThemeToggle() {
    const { preferences, update } = useAppearancePreferences();
    const Current = modeIcons[preferences.mode] ?? Monitor;

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="size-9"
                    aria-label="Appearance settings"
                >
                    <Current className="size-5" />
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-72 space-y-4">
                <div className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        Mode
                    </p>
                    <div className="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1">
                        {MODE_OPTIONS.map(({ value, label }) => {
                            const Icon = modeIcons[value];
                            const active = preferences.mode === value;

                            return (
                                <button
                                    key={value}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() => update({ mode: value })}
                                    className={cn(
                                        'flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                                        active
                                            ? 'bg-background text-foreground shadow-xs'
                                            : 'text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-3.5" />
                                    {label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        Theme
                    </p>
                    <div className="grid grid-cols-3 gap-2">
                        {THEME_OPTIONS.map((theme) => {
                            const active = preferences.theme === theme.value;

                            return (
                                <button
                                    key={theme.value}
                                    type="button"
                                    aria-pressed={active}
                                    onClick={() =>
                                        update({ theme: theme.value })
                                    }
                                    className={cn(
                                        'flex flex-col items-center gap-1.5 rounded-lg border p-2 text-xs transition-colors',
                                        active
                                            ? 'border-primary ring-2 ring-primary/30'
                                            : 'hover:border-foreground/30',
                                    )}
                                >
                                    <span
                                        className="size-6 rounded-md border"
                                        style={{
                                            backgroundColor: theme.swatch,
                                        }}
                                    />
                                    {theme.label}
                                </button>
                            );
                        })}
                    </div>
                </div>

                <div className="space-y-2">
                    <p className="text-xs font-medium text-muted-foreground">
                        Accent
                    </p>
                    <AccentPicker swatchClassName="size-7" />
                </div>

                <div className="flex items-center justify-between gap-3 border-t pt-3">
                    <span className="flex items-center gap-2 text-sm">
                        <Contrast className="size-4 text-muted-foreground" />
                        High contrast
                    </span>
                    <Switch
                        checked={preferences.contrast === 'high'}
                        onCheckedChange={(checked) =>
                            update({ contrast: checked ? 'high' : 'normal' })
                        }
                        aria-label="Toggle high contrast"
                    />
                </div>
            </PopoverContent>
        </Popover>
    );
}
