import { useTranslation } from '@/hooks/use-translation';

/**
 * FND-10 — the first tab stop on every page. Visually hidden until focused,
 * then it jumps keyboard users past the chrome to the `<main>` landmark.
 */
export function SkipToContent() {
    const { t } = useTranslation();

    return (
        <a
            href="#main-content"
            className="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-[100] focus:rounded-md focus:border focus:border-border focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-foreground focus:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            {t('Skip to content')}
        </a>
    );
}
