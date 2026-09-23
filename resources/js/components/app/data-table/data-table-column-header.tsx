import type { Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props<TData, TValue> = {
    column: Column<TData, TValue>;
    title: string;
    className?: string;
};

export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: Props<TData, TValue>) {
    if (!column.getCanSort()) {
        return <span className={className}>{title}</span>;
    }

    const sorted = column.getIsSorted();

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className={cn('-ml-3 h-8', className)}
            onClick={() => column.toggleSorting(sorted === 'asc')}
        >
            {title}
            {sorted === 'asc' ? (
                <ArrowUp />
            ) : sorted === 'desc' ? (
                <ArrowDown />
            ) : (
                <ChevronsUpDown />
            )}
        </Button>
    );
}
