import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
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
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'flex flex-col items-center justify-between gap-3 sm:flex-row',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">
                {prev_cursor === null && next_cursor === null
                    ? t('No results')
                    : t('Showing up to :count entries per page', {
                          count: per_page,
                      })}
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
                    {t('Previous')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || next_cursor === null}
                    onClick={onNext}
                >
                    {t('Next')}
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
