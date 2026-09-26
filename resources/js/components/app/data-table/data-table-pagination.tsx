import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
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
    const { t } = useTranslation();
    const { current_page, last_page, from, to, total } = meta;

    return (
        <div
            className={cn(
                'flex flex-col items-center justify-between gap-3 sm:flex-row',
                className,
            )}
        >
            <p className="text-sm text-muted-foreground">
                {total === 0
                    ? t('No results')
                    : t('Showing :from–:to of :total', {
                          from: from ?? 0,
                          to: to ?? 0,
                          total,
                      })}
            </p>

            <div className="flex items-center gap-2">
                <span className="text-sm text-muted-foreground">
                    {t('Page :current of :last', {
                        current: current_page,
                        last: last_page,
                    })}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || current_page <= 1}
                    onClick={() => onPageChange(current_page - 1)}
                >
                    <ChevronLeft />
                    {t('Previous')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled || current_page >= last_page}
                    onClick={() => onPageChange(current_page + 1)}
                >
                    {t('Next')}
                    <ChevronRight />
                </Button>
            </div>
        </div>
    );
}
