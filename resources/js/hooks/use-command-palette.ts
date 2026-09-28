import { createContext, useContext } from 'react';

export type CommandPaletteContextValue = {
    open: boolean;
    setOpen: (open: boolean) => void;
    toggle: () => void;
};

export const CommandPaletteContext =
    createContext<CommandPaletteContextValue | null>(null);

/** FND-08 — read/open the global command palette from anywhere in the app. */
export function useCommandPalette(): CommandPaletteContextValue {
    const context = useContext(CommandPaletteContext);

    if (!context) {
        throw new Error(
            'useCommandPalette must be used within a CommandPaletteProvider.',
        );
    }

    return context;
}
