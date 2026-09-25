import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PaginationMeta } from '@/types';

type Props = {
    meta: PaginationMeta;
    onPageChange: (page: number) => void;
    disabled?: boolean;
    className?: string;
};

export function DataTablePagination({
    meta,
    onPageChange,
    disabled = false,
    className,
}: Props) {
    const { current_page, last_page, from, to, total } = meta;

    return (
        <div
            className={cn(
                'flex flex-col items-center justify-between gap-3 sm:flex-row',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">
                {total === 0 ? (
                    'No results'
                ) : (
                    <>
                        Showing{' '}
                        <span className="font-medium text-foreground">
                            {from}
                        </span>
                        –
                        <span className="font-medium text-foreground">
                            {to}
                        </span>{' '}
                        of{' '}
                        <span className="font-medium text-foreground">
                            {total}
                        </span>
                    </>
                )}
            </p>

            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">
                    Page {current_page} of {last_page}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || current_page <= 1}
                    onClick={() => onPageChange(current_page - 1)}
                >
                    <ChevronLeft />
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || current_page >= last_page}
                    onClick={() => onPageChange(current_page + 1)}
                >
                    Next
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
