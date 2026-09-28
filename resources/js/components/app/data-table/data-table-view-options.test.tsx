import { describe, expect, it, vi } from 'vitest';
import { DataTableViewOptions } from '@/components/app/data-table/data-table-view-options';
import { renderWithUser, screen } from '@/test/render';

vi.mock(
    '@/hooks/use-translation',
    async () => await import('@/test/support/translation-mock'),
);

const columns = [
    { id: 'name', label: 'User' },
    { id: 'email', label: 'Email' },
];

describe('DataTableViewOptions', () => {
    it('toggles a visible column off', async () => {
        const onToggle = vi.fn();
        const { user } = renderWithUser(
            <DataTableViewOptions
                columns={columns}
                columnVisibility={{}}
                onToggle={onToggle}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'View' }));
        await user.click(
            await screen.findByRole('menuitemcheckbox', { name: 'Email' }),
        );

        expect(onToggle).toHaveBeenCalledWith('email', false);
    });

    it('reflects a hidden column as unchecked', async () => {
        const { user } = renderWithUser(
            <DataTableViewOptions
                columns={columns}
                columnVisibility={{ email: false }}
                onToggle={() => {}}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'View' }));

        const email = await screen.findByRole('menuitemcheckbox', {
            name: 'Email',
        });

        expect(email).toHaveAttribute('aria-checked', 'false');
    });

    it('calls onReset', async () => {
        const onReset = vi.fn();
        const { user } = renderWithUser(
            <DataTableViewOptions
                columns={columns}
                columnVisibility={{ email: false }}
                onToggle={() => {}}
                onReset={onReset}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'View' }));
        await user.click(
            await screen.findByRole('menuitem', { name: 'Reset' }),
        );

        expect(onReset).toHaveBeenCalledOnce();
    });

    it('renders nothing without columns', () => {
        const { container } = renderWithUser(
            <DataTableViewOptions
                columns={[]}
                columnVisibility={{}}
                onToggle={() => {}}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
