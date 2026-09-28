import type { ReactElement, ReactNode } from 'react';
import {
    type RenderOptions,
    type RenderResult,
    render as rtlRender,
} from '@testing-library/react';
import userEvent, { type UserEvent } from '@testing-library/user-event';
import { TooltipProvider } from '@/components/ui/tooltip';

/**
 * Shared RTL helpers (FND-09). Import `render`/`renderWithUser` from here so
 * every component test gets the same provider tree.
 */

type ProvidersProps = {
    children: ReactNode;
};

/** Providers that shared components assume are present. */
export function Providers({ children }: ProvidersProps): ReactElement {
    return <TooltipProvider delayDuration={0}>{children}</TooltipProvider>;
}

/** Render `ui` inside the shared provider tree. */
export function render(
    ui: ReactElement,
    options?: Omit<RenderOptions, 'wrapper'>,
): RenderResult {
    return rtlRender(ui, { wrapper: Providers, ...options });
}

/**
 * Render `ui` and return a `userEvent` instance for interaction-driven tests.
 */
export function renderWithUser(
    ui: ReactElement,
    options?: Omit<RenderOptions, 'wrapper'>,
): RenderResult & { user: UserEvent } {
    return { user: userEvent.setup(), ...render(ui, options) };
}

export {
    act,
    fireEvent,
    screen,
    waitFor,
    waitForElementToBeRemoved,
    within,
} from '@testing-library/react';
export { userEvent };
