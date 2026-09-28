import { describe, expect, it, vi } from 'vitest';
import { ErrorState } from '@/components/feedback/error-state';
import { render, renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

describe('ErrorState', () => {
    it('renders as an alert with the failure message', () => {
        render(
            <ErrorState
                title="Could not load this report"
                description="The request failed. Try again in a moment."
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Could not load this report',
        );
        expect(
            screen.getByText('The request failed. Try again in a moment.'),
        ).toBeInTheDocument();
    });

    it('calls onRetry when the retry button is pressed', async () => {
        const onRetry = vi.fn();
        const { user } = renderWithUser(
            <ErrorState title="Could not load" onRetry={onRetry} />,
        );

        await user.click(screen.getByRole('button', { name: 'Retry' }));

        expect(onRetry).toHaveBeenCalledOnce();
    });

    it('shows the retry button as busy while retrying', () => {
        render(
            <ErrorState title="Could not load" onRetry={() => {}} retrying />,
        );

        const retry = screen.getByRole('button', { name: /Retry/ });

        expect(retry).toBeDisabled();
        expect(retry).toHaveAttribute('aria-busy', 'true');
    });

    it('reveals raw details behind a disclosure', () => {
        const { container } = render(
            <ErrorState title="Could not load" details="500 server error" />,
        );

        expect(screen.getByText('Show details')).toBeInTheDocument();
        expect(screen.getByText('500 server error')).toBeInTheDocument();
        expect(container.querySelector('details')).not.toBeNull();
    });
});
