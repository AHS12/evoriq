import type { TranslationHook } from '@/hooks/use-translation';

/**
 * A drop-in `useTranslation` substitute for component tests (FND-09).
 *
 * Opt in with:
 *
 * ```ts
 * vi.mock('@/hooks/use-translation', async () =>
 *     await import('@/test/support/translation-mock'),
 * );
 * ```
 *
 * Unmapped keys are returned verbatim, so assertions can use the English source
 * string; seed a dictionary with {@link setTranslations}.
 */

let dictionary: Record<string, string> = {};

export function setTranslations(next: Record<string, string>): void {
    dictionary = next;
}

export function resetTranslations(): void {
    dictionary = {};
}

function interpolate(
    line: string,
    replacements?: Record<string, string | number>,
): string {
    if (!replacements) {
        return line;
    }

    return line.replace(/:([A-Za-z_]+)/g, (match, name: string) => {
        const value = replacements[name] ?? replacements[name.toLowerCase()];

        return value === undefined ? match : String(value);
    });
}

export function useTranslation(): TranslationHook {
    return {
        t: (key, replacements) =>
            interpolate(dictionary[key] ?? key, replacements),
        tChoice: (key, count, replacements) =>
            interpolate(dictionary[key] ?? key, { ...replacements, count }),
        locale: 'en',
        supported: [],
    };
}
