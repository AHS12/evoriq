import { describe, expect, it, vi } from 'vitest';
import { EmptyState } from '@/components/app/empty-state';
import { Timeline, TimelineGroup } from '@/components/timeline/timeline';
import {
    TimelineContent,
    TimelineItem,
} from '@/components/timeline/timeline-item';
import { TimelineSkeleton } from '@/components/timeline/timeline-skeleton';
import { TimelineTrack } from '@/components/timeline/timeline-track';
import { render, renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

describe('Timeline', () => {
    it('renders an ordered list with an accessible label', () => {
        render(
            <Timeline aria-label="Pipeline events">
                <TimelineItem>
                    <TimelineContent title="Started" />
                </TimelineItem>
            </Timeline>,
        );

        expect(
            screen.getByRole('list', { name: 'Pipeline events' }),
        ).toBeInTheDocument();
    });

    it('renders groups and their items', () => {
        render(
            <Timeline>
                <TimelineGroup label="Today">
                    <TimelineItem>
                        <TimelineContent title="Fetched" />
                    </TimelineItem>
                </TimelineGroup>
            </Timeline>,
        );

        expect(screen.getByText('Today')).toBeInTheDocument();
        expect(screen.getByText('Fetched')).toBeInTheDocument();
    });

    it('renders the provided empty state when there are no children', () => {
        render(<Timeline emptyState={<EmptyState title="No events yet" />} />);

        expect(screen.getByText('No events yet')).toBeInTheDocument();
    });

    it('renders nothing when empty without an empty state', () => {
        const { container } = render(<Timeline />);

        expect(container.firstChild).toBeNull();
    });
});

describe('TimelineItem', () => {
    it('renders the time and a duration chip', () => {
        const { container } = render(
            <Timeline>
                <TimelineItem time="2026-09-28T01:00:00Z" durationMs={65000}>
                    <TimelineContent
                        title="Reading source file"
                        description="12,000 rows"
                    />
                </TimelineItem>
            </Timeline>,
        );

        const time = container.querySelector('time');

        expect(time).toHaveAttribute('datetime', '2026-09-28T01:00:00.000Z');
        expect(screen.getByText('Reading source file')).toBeInTheDocument();
        expect(screen.getByText('1m')).toBeInTheDocument();
    });

    it('toggles an expandable item and exposes its ARIA state', async () => {
        const { user } = renderWithUser(
            <Timeline>
                <TimelineItem expandable>
                    <TimelineContent title="Write rows">
                        <p>Hidden detail</p>
                    </TimelineContent>
                </TimelineItem>
            </Timeline>,
        );

        const button = screen.getByRole('button', { name: /Write rows/ });

        expect(button).toHaveAttribute('aria-expanded', 'false');

        const controls = button.getAttribute('aria-controls') ?? '';
        const region = document.getElementById(controls);

        expect(region).not.toBeNull();
        expect(region).toHaveAttribute('inert');

        await user.click(button);

        expect(button).toHaveAttribute('aria-expanded', 'true');
        expect(region).not.toHaveAttribute('inert');
    });

    it('renders detail without a toggle when not expandable', () => {
        render(
            <Timeline>
                <TimelineItem>
                    <TimelineContent title="Finished">
                        <p>Always visible</p>
                    </TimelineContent>
                </TimelineItem>
            </Timeline>,
        );

        expect(screen.getByText('Always visible')).toBeInTheDocument();
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});

describe('TimelineTrack', () => {
    it('renders proportional, named segments', () => {
        render(
            <TimelineTrack
                ariaLabel="Stages"
                segments={[
                    {
                        key: 'fetch',
                        label: 'Fetch',
                        status: 'completed',
                        ratio: 1,
                    },
                    {
                        key: 'write',
                        label: 'Write',
                        status: 'running',
                        ratio: 3,
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('list', { name: 'Stages' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('listitem', { name: 'Fetch: Completed' }),
        ).toHaveStyle({ width: '25%' });
        expect(
            screen.getByRole('listitem', { name: 'Write: Running' }),
        ).toHaveStyle({ width: '75%' });
    });

    it('makes a failed segment focusable', () => {
        render(
            <TimelineTrack
                segments={[
                    {
                        key: 'write',
                        label: 'Write',
                        status: 'failed',
                        ratio: 1,
                        error: 'Disk full',
                    },
                ]}
            />,
        );

        expect(
            screen.getByRole('listitem', { name: 'Write: Failed' }),
        ).toHaveAttribute('tabindex', '0');
    });
});

describe('TimelineSkeleton', () => {
    it('renders the requested number of placeholder rows', () => {
        const { container } = render(<TimelineSkeleton rows={3} />);

        expect(
            container.querySelector('[data-slot="timeline-skeleton"]'),
        ).toBeInTheDocument();
        expect(
            container.querySelectorAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThanOrEqual(3);
    });
});
