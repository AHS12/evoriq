import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
    type SortingState,
    type VisibilityState,
} from '@tanstack/react-table';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { TableSkeleton } from '@/components/feedback/skeleton-table';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { TableDensity } from '@/lib/table-preferences';
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
    density?: TableDensity;
    columnVisibility?: VisibilityState;
    onColumnVisibilityChange?: (visibility: VisibilityState) => void;
    stickyHeader?: boolean;
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
    density = 'comfortable',
    columnVisibility,
    onColumnVisibilityChange,
    stickyHeader = false,
    className,
}: Props<TData, TValue>) {
    const table = useReactTable({
        data,
        columns,
        state: { sorting, columnVisibility },
        onSortingChange: onSortingChange
            ? (updater) => {
                  onSortingChange(
                      typeof updater === 'function'
                          ? updater(sorting)
                          : updater,
                  );
              }
            : undefined,
        onColumnVisibilityChange: onColumnVisibilityChange
            ? (updater) => {
                  onColumnVisibilityChange(
                      typeof updater === 'function'
                          ? updater(columnVisibility ?? {})
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
                'rounded-xl border transition-opacity duration-fast ease-standard',
                stickyHeader ? 'max-h-[70vh] overflow-auto' : 'overflow-hidden',
                isLoading &&
                    rows.length > 0 &&
                    'pointer-events-none opacity-60',
                className,
            )}
        >
            <Table
                className={cn(
                    density === 'compact' &&
                        '[&_td]:py-1.5 [&_th]:h-8 [&_th]:py-1.5',
                    stickyHeader &&
                        '[&_[data-slot=table-container]]:overflow-visible',
                )}
            >
                <TableHeader
                    className={cn(
                        stickyHeader &&
                            'sticky top-0 z-10 bg-background [&_tr]:border-b',
                    )}
                >
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
                        <TableSkeleton columns={columns.length} rows={5} />
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
