import type { ColumnDef } from '@tanstack/react-table';
import { describe, expect, it } from 'vitest';
import { getHideableColumns } from '@/lib/data-table-columns';

type Row = { name: string; email: string };

describe('getHideableColumns', () => {
    it('derives ids and labels from meta', () => {
        const columns: ColumnDef<Row>[] = [
            { accessorKey: 'name', header: 'Name', meta: { label: 'User' } },
            { accessorKey: 'email', header: 'Email', meta: { label: 'Email' } },
        ];

        expect(getHideableColumns(columns)).toEqual([
            { id: 'name', label: 'User' },
            { id: 'email', label: 'Email' },
        ]);
    });

    it('skips columns that cannot be hidden', () => {
        const columns: ColumnDef<Row>[] = [
            { accessorKey: 'name', header: 'Name' },
            { id: 'actions', enableHiding: false, header: 'Actions' },
            {
                accessorKey: 'email',
                header: 'Email',
                meta: { hideable: false },
            },
        ];

        expect(getHideableColumns(columns)).toEqual([
            { id: 'name', label: 'name' },
        ]);
    });

    it('joins nested accessor keys and ignores columns without an id', () => {
        const columns = [
            { accessorKey: ['profile', 'city'], header: 'City' },
            { header: 'No id' },
        ] as unknown as ColumnDef<Record<string, unknown>>[];

        expect(getHideableColumns(columns)).toEqual([
            { id: 'profile.city', label: 'profile.city' },
        ]);
    });
});
