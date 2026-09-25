import { usePage } from '@inertiajs/react';
import type { I18n } from '@/types/i18n';

type Replacements = Record<string, string | number>;

export type TranslationHook = {
    t: (key: string, replacements?: Replacements) => string;
    tChoice: (
        key: string,
        count: number,
        replacements?: Replacements,
    ) => string;
    locale: string;
    supported: I18n['supported'];
};

/**
 * Laravel-style translations from the shared i18n prop. The dictionary and
 * locale are always delivered by the server (HandleInertiaRequests), so the
 * hook is read-only; switching a locale persists it server-side and re-renders
 * with a fresh dictionary.
 */
export function useTranslation(): TranslationHook {
    const { locale, translations, supported } = usePage().props.i18n;

    const t = (key: string, replacements?: Replacements): string =>
        interpolate(translations[key] ?? key, replacements);

    const tChoice = (
        key: string,
        count: number,
        replacements?: Replacements,
    ): string =>
        interpolate(pluralize(translations[key] ?? key, count), {
            ...replacements,
            count,
        });

    return { t, tChoice, locale, supported };
}

/**
 * Laravel-style `:placeholder` interpolation, including `:NAME` (uppercased
 * value) and `:Name` (capitalized value).
 */
function interpolate(line: string, replacements?: Replacements): string {
    if (!replacements) {
        return line;
    }

    return line.replace(/:([A-Za-z_]+)/g, (match, name: string) => {
        const value =
            name in replacements
                ? replacements[name]
                : (replacements[name.toLowerCase()] ?? undefined);

        if (value === undefined) {
            return match;
        }

        const text = String(value);

        if (name === name.toUpperCase()) {
            return text.toUpperCase();
        }

        if (name[0] === name[0]?.toUpperCase()) {
            return text.charAt(0).toUpperCase() + text.slice(1);
        }

        return text;
    });
}

/**
 * Laravel MessageSelector-style pluralization: explicit ranges
 * (`{0} None|[1,19] Some|[20,*] Many`) or simple forms (`one|many`).
 */
function pluralize(line: string, count: number): string {
    const segments = line.split('|');

    for (const segment of segments) {
        const match = segment.match(/^[{[]([^[\]{}]+)[\]}](.*)$/);

        if (match && matchesRange(match[1], count)) {
            return match[2].trim();
        }
    }

    if (segments.length === 1 || count === 1) {
        return segments[0].trim();
    }

    return segments[segments.length - 1].trim();
}

function matchesRange(range: string, count: number): boolean {
    return range.split(',').some((part) => {
        if (part === '*') {
            return true;
        }

        const bounds = part.split('-');

        if (bounds.length === 2) {
            return count >= Number(bounds[0]) && count <= Number(bounds[1]);
        }

        return count === Number(part);
    });
}
