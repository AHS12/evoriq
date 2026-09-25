import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { CursorPaginationMeta } from '@/types';

type Props = {
    meta: CursorPaginationMeta;
    onPrevious: () => void;
    onNext: () => void;
    disabled?: boolean;
    className?: string;
};

export function DataTableCursorPagination({
    meta,
    onPrevious,
    onNext,
    disabled = false,
    className,
}: Props) {
    const { per_page, next_cursor, prev_cursor } = meta;

    return (
        <div
            className={cn(
                'flex flex-col items-center justify-between gap-3 sm:flex-row',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">
                {prev_cursor === null && next_cursor === null ? (
                    'No results'
                ) : (
                    <>
                        Showing up to{' '}
                        <span className="font-medium text-foreground">
                            {per_page}
                        </span>{' '}
                        entries per page
                    </>
                )}
            </p>

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || prev_cursor === null}
                    onClick={onPrevious}
                >
                    <ChevronLeft />
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || next_cursor === null}
                    onClick={onNext}
                >
                    Next
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
