import { describe, expect, it, vi } from 'vitest';
import { SkipToContent } from '@/components/app/skip-to-content';
import { render, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

describe('SkipToContent', () => {
    it('links to the main landmark', () => {
        render(<SkipToContent />);

        expect(
            screen.getByRole('link', { name: 'Skip to content' }),
        ).toHaveAttribute('href', '#main-content');
    });

    it('is visually hidden until focused', () => {
        render(<SkipToContent />);

        const link = screen.getByRole('link', { name: 'Skip to content' });

        expect(link.className).toContain('sr-only');
        expect(link.className).toContain('focus:not-sr-only');
    });
});
