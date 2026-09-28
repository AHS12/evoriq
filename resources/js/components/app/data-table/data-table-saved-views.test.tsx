import { describe, expect, it, vi } from 'vitest';
import { DataTableSavedViews } from '@/components/app/data-table/data-table-saved-views';
import type { TableSavedView } from '@/lib/table-preferences';
import { renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

const view: TableSavedView = {
    id: 'v1',
    name: 'Active users',
    filters: { status: 'active' },
    createdAt: '2026-01-01T00:00:00.000Z',
};

function setup(
    overrides: Partial<Parameters<typeof DataTableSavedViews>[0]> = {},
) {
    const props = {
        views: [],
        filters: {},
        onApply: vi.fn(),
        onSave: vi.fn(),
        onRename: vi.fn(),
        onDelete: vi.fn(),
        ...overrides,
    };

    return { ...renderWithUser(<DataTableSavedViews {...props} />), props };
}

describe('DataTableSavedViews', () => {
    it('shows an empty prompt when there are no views', async () => {
        const { user } = setup();

        await user.click(screen.getByRole('button', { name: /Saved views/ }));

        expect(
            await screen.findByText('No saved views yet'),
        ).toBeInTheDocument();
    });

    it('applies a saved view on click', async () => {
        const { user, props } = setup({ views: [view] });

        await user.click(screen.getByRole('button', { name: /Saved views/ }));
        await user.click(
            await screen.findByRole('menuitem', { name: /Active users/ }),
        );

        expect(props.onApply).toHaveBeenCalledWith({ status: 'active' });
    });

    it('saves the current filters through the dialog', async () => {
        const { user, props } = setup({ filters: { status: 'active' } });

        await user.click(screen.getByRole('button', { name: /Saved views/ }));
        await user.click(
            await screen.findByRole('menuitem', {
                name: 'Save current view…',
            }),
        );

        await user.type(
            await screen.findByLabelText('View name'),
            'My active users',
        );
        await user.click(screen.getByRole('button', { name: 'Save' }));

        expect(props.onSave).toHaveBeenCalledWith('My active users', {
            status: 'active',
        });
    });

    it('disables saving until a name is entered', async () => {
        const { user } = setup();

        await user.click(screen.getByRole('button', { name: /Saved views/ }));
        await user.click(
            await screen.findByRole('menuitem', {
                name: 'Save current view…',
            }),
        );

        expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled();
    });
});
