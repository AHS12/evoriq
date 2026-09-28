import { SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import type { ColumnOption } from '@/lib/data-table-columns';

type Props = {
    columns: ColumnOption[];
    columnVisibility: Record<string, boolean>;
    onToggle: (id: string, visible: boolean) => void;
    onReset?: () => void;
    disabled?: boolean;
    className?: string;
};

/** FND-07 — show/hide table columns; persisted per table by the caller. */
export function DataTableViewOptions({
    columns,
    columnVisibility,
    onToggle,
    onReset,
    disabled,
    className,
}: Props) {
    const { t } = useTranslation();

    if (columns.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={disabled}
                    className={className}
                >
                    <SlidersHorizontal />
                    {t('View')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>{t('Toggle columns')}</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {columns.map((column) => (
                    <DropdownMenuCheckboxItem
                        key={column.id}
                        checked={columnVisibility[column.id] !== false}
                        onCheckedChange={(checked) =>
                            onToggle(column.id, checked === true)
                        }
                        onSelect={(event) => event.preventDefault()}
                    >
                        {column.label}
                    </DropdownMenuCheckboxItem>
                ))}
                {onReset && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={onReset}>
                            {t('Reset')}
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
