import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    /** Number of placeholder rows to render. */
    rows?: number;
    /** Number of columns per row. */
    columns: number;
    className?: string;
};

/**
 * Placeholder rows for a `<TableBody>`. Prefer this over a spinner when the
 * shape of the incoming content is known (tables, lists).
 */
export function TableSkeleton({ rows = 5, columns, className }: Props) {
    return (
        <>
            {Array.from({ length: rows }).map((_, rowIndex) => (
                <TableRow
                    key={rowIndex}
                    aria-hidden
                    className={cn('hover:bg-transparent', className)}
                >
                    {Array.from({ length: columns }).map((__, cellIndex) => (
                        <TableCell key={cellIndex}>
                            <Skeleton className="h-5 w-full" />
                        </TableCell>
                    ))}
                </TableRow>
            ))}
        </>
    );
}

/** Standalone table-shaped skeleton for page-level loading states. */
export function TableBlockSkeleton({ rows = 5, columns, className }: Props) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border transition-opacity duration-fast ease-standard',
                className,
            )}
        >
            <Table>
                <TableHeader>
                    <TableRow className="hover:bg-transparent">
                        {Array.from({ length: columns }).map((_, index) => (
                            <TableHead key={index}>
                                <Skeleton className="h-4 w-24" />
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableSkeleton rows={rows} columns={columns} />
                </TableBody>
            </Table>
        </div>
    );
}
