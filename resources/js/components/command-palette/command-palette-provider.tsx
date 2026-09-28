import {
    useCallback,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';
import { CommandPalette } from '@/components/command-palette/command-palette';
import {
    CommandPaletteContext,
    type CommandPaletteContextValue,
} from '@/hooks/use-command-palette';

/**
 * FND-08 — owns the palette's open state and the global ⌘K / Ctrl+K shortcut,
 * and renders the palette host once for the whole app.
 */
export function CommandPaletteProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);

    const toggle = useCallback(() => setOpen((previous) => !previous), []);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                !event.altKey &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((previous) => !previous);
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    const value = useMemo<CommandPaletteContextValue>(
        () => ({ open, setOpen, toggle }),
        [open, toggle],
    );

    return (
        <CommandPaletteContext.Provider value={value}>
            {children}
            <CommandPalette />
        </CommandPaletteContext.Provider>
    );
}
