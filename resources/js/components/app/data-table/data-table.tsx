import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
    type SortingState,
} from '@tanstack/react-table';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';

export type DataTableEmptyState = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
};

type Props<TData, TValue> = {
    columns: ColumnDef<TData, TValue>[];
    data: TData[];
    isLoading?: boolean;
    sorting?: SortingState;
    onSortingChange?: (sorting: SortingState) => void;
    onRowClick?: (row: TData) => void;
    emptyState?: DataTableEmptyState;
    className?: string;
};

export function DataTable<TData, TValue>({
    columns,
    data,
    isLoading = false,
    sorting = [],
    onSortingChange,
    onRowClick,
    emptyState,
    className,
}: Props<TData, TValue>) {
    const table = useReactTable({
        data,
        columns,
        state: { sorting },
        onSortingChange: onSortingChange
            ? (updater) => {
                  onSortingChange(
                      typeof updater === 'function'
                          ? updater(sorting)
                          : updater,
                  );
              }
            : undefined,
        manualSorting: true,
        manualPagination: true,
        getCoreRowModel: getCoreRowModel(),
    });

    const empty = emptyState ?? { title: 'No results' };
    const rows = table.getRowModel().rows;

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border transition-opacity',
                isLoading &&
                    rows.length > 0 &&
                    'pointer-events-none opacity-60',
                className,
            )}
        >
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead key={header.id}>
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(
                                              header.column.columnDef.header,
                                              header.getContext(),
                                          )}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {isLoading && rows.length === 0 ? (
                        columns.map((column, index) => (
                            <TableRow key={column.id ?? `skeleton-${index}`}>
                                {columns.map((cell, cellIndex) => (
                                    <TableCell key={cell.id ?? cellIndex}>
                                        <Skeleton className="h-5 w-full" />
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : rows.length > 0 ? (
                        rows.map((row) => (
                            <TableRow
                                key={row.id}
                                className={cn(
                                    onRowClick &&
                                        'cursor-pointer hover:bg-muted/50',
                                )}
                                onClick={
                                    onRowClick
                                        ? () => onRowClick(row.original)
                                        : undefined
                                }
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell key={cell.id}>
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext(),
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={columns.length} className="p-0">
                                <EmptyState
                                    icon={empty.icon}
                                    title={empty.title}
                                    description={empty.description}
                                    action={empty.action}
                                    className="rounded-none border-0"
                                />
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
