import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { AppearanceMode } from '@/hooks/use-appearance';
import { useAppearancePreferences } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const modes: { value: AppearanceMode; label: string; icon: LucideIcon }[] = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
];

export default function AppearanceTabs({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { preferences, update } = useAppearancePreferences();

    return (
        <div
            role="radiogroup"
            aria-label="Color mode"
            className={cn(
                'inline-flex gap-1 rounded-lg bg-muted p-1',
                className,
            )}
            {...props}
        >
            {modes.map(({ value, label, icon: Icon }) => (
                <button
                    key={value}
                    type="button"
                    role="radio"
                    aria-checked={preferences.mode === value}
                    onClick={() => update({ mode: value })}
                    className={cn(
                        'flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-sm transition-colors',
                        preferences.mode === value
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                    )}
                >
                    <Icon className="size-4" />
                    {label}
                </button>
            ))}
        </div>
    );
}
