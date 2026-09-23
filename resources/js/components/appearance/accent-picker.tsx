import { Check } from 'lucide-react';
import { useAppearancePreferences } from '@/hooks/use-appearance';
import { ACCENT_PRESETS } from '@/lib/appearance';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    swatchClassName?: string;
};

const RAINBOW =
    'conic-gradient(from 0deg, #ef4444, #f59e0b, #10b981, #3b82f6, #8b5cf6, #e11d48, #ef4444)';

export function AccentPicker({ className, swatchClassName = 'size-9' }: Props) {
    const { preferences, update } = useAppearancePreferences();

    const isPreset = ACCENT_PRESETS.some(
        (preset) =>
            preset.color.toLowerCase() === preferences.accent.toLowerCase(),
    );

    return (
        <div className={cn('flex flex-wrap items-center gap-3', className)}>
            {ACCENT_PRESETS.map((preset) => {
                const active =
                    preferences.accent.toLowerCase() ===
                    preset.color.toLowerCase();

                return (
                    <button
                        key={preset.color}
                        type="button"
                        aria-label={preset.label}
                        aria-pressed={active}
                        onClick={() => update({ accent: preset.color })}
                        className={cn(
                            'flex items-center justify-center rounded-full ring-offset-2 ring-offset-background transition',
                            swatchClassName,
                            active
                                ? 'ring-2 ring-foreground'
                                : 'hover:scale-110',
                        )}
                        style={{ backgroundColor: preset.color }}
                    >
                        {active && <Check className="size-4 text-white" />}
                    </button>
                );
            })}

            <label
                title="Custom color"
                className={cn(
                    'relative flex cursor-pointer items-center justify-center overflow-hidden rounded-full border ring-offset-2 ring-offset-background transition',
                    swatchClassName,
                    !isPreset ? 'ring-2 ring-foreground' : 'hover:scale-110',
                )}
                style={
                    isPreset
                        ? { backgroundImage: RAINBOW }
                        : { backgroundColor: preferences.accent }
                }
            >
                <input
                    type="color"
                    value={preferences.accent}
                    onChange={(event) => update({ accent: event.target.value })}
                    className="absolute inset-0 size-full cursor-pointer opacity-0"
                    aria-label="Custom accent color"
                />
                {!isPreset && (
                    <Check className="pointer-events-none size-4 text-white" />
                )}
            </label>
        </div>
    );
}
