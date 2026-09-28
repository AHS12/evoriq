import { describe, expect, it, vi } from 'vitest';
import { InlineAlert } from '@/components/feedback/inline-alert';
import { render, renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

describe('InlineAlert', () => {
    it('renders an info message as a polite status', () => {
        render(<InlineAlert tone="info" title="Syncing in the background" />);

        expect(screen.getByRole('status')).toHaveTextContent(
            'Syncing in the background',
        );
    });

    it('renders warning and error tones as alerts', () => {
        render(<InlineAlert tone="error" title="Rate limited" />);

        expect(screen.getByRole('alert')).toHaveTextContent('Rate limited');
    });

    it('renders a description and an action', () => {
        render(
            <InlineAlert
                title="Live updates are paused."
                action={<button type="button">Resume</button>}
            >
                Retrying automatically when you resume.
            </InlineAlert>,
        );

        expect(
            screen.getByText('Retrying automatically when you resume.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Resume' }),
        ).toBeInTheDocument();
    });

    it('calls onDismiss from the labelled dismiss button', async () => {
        const onDismiss = vi.fn();
        const { user } = renderWithUser(
            <InlineAlert title="Paused" onDismiss={onDismiss} />,
        );

        await user.click(screen.getByRole('button', { name: 'Dismiss' }));

        expect(onDismiss).toHaveBeenCalledOnce();
    });
});
