import type { ColumnDef } from '@tanstack/react-table';
import { describe, expect, it } from 'vitest';
import { DataTable } from '@/components/app/data-table/data-table';
import { render, screen } from '@/test/render';

type Row = { name: string; email: string };

const columns: ColumnDef<Row>[] = [
    {
        accessorKey: 'name',
        header: 'Name',
        cell: ({ row }) => row.original.name,
    },
    {
        accessorKey: 'email',
        header: 'Email',
        cell: ({ row }) => row.original.email,
    },
];

describe('DataTable', () => {
    it('renders the rows', () => {
        render(
            <DataTable
                columns={columns}
                data={[{ name: 'Ada', email: 'ada@example.test' }]}
            />,
        );

        expect(screen.getByText('Ada')).toBeInTheDocument();
        expect(screen.getByText('ada@example.test')).toBeInTheDocument();
    });

    it('applies the compact density class', () => {
        const { container } = render(
            <DataTable
                columns={columns}
                data={[{ name: 'Ada', email: 'ada@example.test' }]}
                density="compact"
            />,
        );

        expect(container.querySelector('table')?.className).toContain(
            '[&_td]:py-1.5',
        );
    });

    it('sticks the header when requested', () => {
        const { container } = render(
            <DataTable
                columns={columns}
                data={[{ name: 'Ada', email: 'ada@example.test' }]}
                stickyHeader
            />,
        );

        expect(container.firstElementChild?.className).toContain(
            'overflow-auto',
        );
        expect(container.querySelector('thead')?.className).toContain('sticky');
    });

    it('hides columns via column visibility', () => {
        render(
            <DataTable
                columns={columns}
                data={[{ name: 'Ada', email: 'ada@example.test' }]}
                columnVisibility={{ email: false }}
            />,
        );

        expect(screen.getByText('Name')).toBeInTheDocument();
        expect(screen.queryByText('Email')).not.toBeInTheDocument();
    });

    it('shows the empty state with no rows', () => {
        render(
            <DataTable
                columns={columns}
                data={[]}
                emptyState={{ title: 'No users found' }}
            />,
        );

        expect(screen.getByText('No users found')).toBeInTheDocument();
    });

    it('shows a skeleton while loading', () => {
        const { container } = render(
            <DataTable columns={columns} data={[]} isLoading />,
        );

        expect(
            container.querySelectorAll('[data-slot="skeleton"]').length,
        ).toBeGreaterThan(0);
    });
});
