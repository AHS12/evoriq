import { Activity } from 'lucide-react';
import { describe, expect, it } from 'vitest';
import { EmptyState } from '@/components/app/empty-state';
import { render, screen } from '@/test/render';

describe('EmptyState', () => {
    it('renders the title, description and action', () => {
        render(
            <EmptyState
                icon={Activity}
                title="Nothing running"
                description="Start an import to see progress here."
                action={<button type="button">Start</button>}
            />,
        );

        expect(screen.getByText('Nothing running')).toBeInTheDocument();
        expect(
            screen.getByText('Start an import to see progress here.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Start' }),
        ).toBeInTheDocument();
    });

    it('supports a compact variant', () => {
        const { container } = render(
            <EmptyState title="No events yet" variant="compact" />,
        );

        expect(
            container.querySelector('[data-slot="empty-state"]'),
        ).toHaveAttribute('data-variant', 'compact');
    });
});
