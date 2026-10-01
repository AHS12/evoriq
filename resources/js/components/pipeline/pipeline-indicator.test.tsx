import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PipelineIndicator } from '@/components/pipeline/pipeline-indicator';
import { usePipelineStatus } from '@/hooks/use-pipeline-status';
import { render, screen } from '@/test/render';
import type { PipelineStatus } from '@/types';

vi.mock('@/hooks/use-translation', async () => {
    return await import('@/test/support/translation-mock');
});

vi.mock('@/hooks/use-pipeline-status', () => ({
    usePipelineStatus: vi.fn(),
}));

const mocked = vi.mocked(usePipelineStatus);

const IDLE: PipelineStatus = {
    active_count: 0,
    queued_count: 0,
    processing_count: 0,
    failed_recent: 0,
    worst_status: 'success',
    runs: [],
    freshness: null,
    api_budget: null,
};

function withStatus(overrides: Partial<PipelineStatus> = {}): void {
    mocked.mockReturnValue({
        status: { ...IDLE, ...overrides },
        ready: true,
        live: true,
        pause: vi.fn(),
        resume: vi.fn(),
    });
}

beforeEach(() => {
    mocked.mockReset();
});

describe('PipelineIndicator', () => {
    it('stays quiet when idle', () => {
        withStatus();

        const { container } = render(<PipelineIndicator />);

        expect(container.querySelector('[data-slot]')).toBeNull();
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('shows the running count while work is active', () => {
        withStatus({
            active_count: 3,
            processing_count: 3,
            worst_status: 'info',
        });

        render(<PipelineIndicator />);

        expect(
            screen.getByRole('button', {
                name: 'Background jobs: 3 running',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('3 running')).toBeInTheDocument();
    });

    it('surfaces recent failures when idle but failing', () => {
        withStatus({ failed_recent: 2, worst_status: 'error' });

        render(<PipelineIndicator />);

        expect(
            screen.getByRole('button', {
                name: 'Background jobs: 2 recent failures',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('2 failed')).toBeInTheDocument();
    });

    it('renders a skeleton until the shared prop is ready', () => {
        mocked.mockReturnValue({
            status: IDLE,
            ready: false,
            live: false,
            pause: vi.fn(),
            resume: vi.fn(),
        });

        const { container } = render(<PipelineIndicator />);

        expect(
            container.querySelector('[data-slot="skeleton"]'),
        ).toBeInTheDocument();
    });
});
