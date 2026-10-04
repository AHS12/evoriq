import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiUsageIndicator } from '@/components/pipeline/api-usage-indicator';
import { useApiUsage } from '@/hooks/use-api-usage';
import { render, screen } from '@/test/render';
import type { ApiUsage } from '@/types';

vi.mock('@/hooks/use-translation', async () => {
    return await import('@/test/support/translation-mock');
});

vi.mock('@/hooks/use-api-usage', () => ({
    useApiUsage: vi.fn(),
}));

const mocked = vi.mocked(useApiUsage);

const FREE: ApiUsage = {
    window_type: 'hour',
    used: 5,
    limit: 30,
    remaining: 25,
    resets_at: new Date(Date.now() + 600_000).toISOString(),
    resets_in: 600,
    last_request_at: null,
    plan: 'free',
    low: false,
    exhausted: false,
};

function withUsage(usage: ApiUsage | null): void {
    mocked.mockReturnValue({ usage, pause: vi.fn(), resume: vi.fn() });
}

beforeEach(() => {
    mocked.mockReset();
});

describe('ApiUsageIndicator', () => {
    it('hides when there is no connection', () => {
        withUsage(null);

        const { container } = render(<ApiUsageIndicator />);

        expect(container.querySelector('[data-slot]')).toBeNull();
        expect(screen.queryByRole('button')).toBeNull();
    });

    it('shows remaining/limit on a free plan', () => {
        withUsage(FREE);

        render(<ApiUsageIndicator />);

        expect(
            screen.getByRole('button', {
                name: 'Clockify API: 25 of 30 requests remaining',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('API 25/30')).toBeInTheDocument();
    });

    it('shows a per-second rate on a paid plan', () => {
        withUsage({
            ...FREE,
            window_type: 'second',
            plan: 'paid',
            limit: 50,
            remaining: 50,
            used: 0,
        });

        render(<ApiUsageIndicator />);

        expect(
            screen.getByRole('button', {
                name: 'Clockify API: up to 50 requests per second',
            }),
        ).toBeInTheDocument();
        expect(screen.getByText('API 50/s')).toBeInTheDocument();
    });
});
