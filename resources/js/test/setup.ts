import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

/**
 * Global test setup (FND-09).
 *
 * - registers the `@testing-library/jest-dom` matchers on Vitest's `expect`;
 * - unmounts rendered trees between tests;
 * - polyfills browser APIs jsdom lacks but our components use.
 */

afterEach(() => {
    cleanup();
});

// `useReducedMotion` reads matchMedia; jsdom does not implement it.
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string): MediaQueryList =>
        ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        }) as unknown as MediaQueryList,
});

// Recharts' ResponsiveContainer observes its box; jsdom has no ResizeObserver.
class ResizeObserverStub {
    observe(): void {}

    unobserve(): void {}

    disconnect(): void {}
}

if (!('ResizeObserver' in globalThis)) {
    Object.defineProperty(globalThis, 'ResizeObserver', {
        writable: true,
        value: ResizeObserverStub,
    });
}
