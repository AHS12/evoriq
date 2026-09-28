import { AlignJustify, Rows3 } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import type { TableDensity } from '@/lib/table-preferences';
import { cn } from '@/lib/utils';

type Props = {
    value: TableDensity;
    onChange: (density: TableDensity) => void;
    disabled?: boolean;
    className?: string;
};

/** FND-07 — comfortable / compact row density for a shared table. */
export function DataTableDensityToggle({
    value,
    onChange,
    disabled,
    className,
}: Props) {
    const { t } = useTranslation();

    return (
        <ToggleGroup
            type="single"
            value={value}
            onValueChange={(next) => {
                if (next) {
                    onChange(next as TableDensity);
                }
            }}
            variant="outline"
            size="sm"
            disabled={disabled}
            aria-label={t('Row density')}
            className={cn(className)}
        >
            <ToggleGroupItem value="comfortable" aria-label={t('Comfortable')}>
                <Rows3 />
            </ToggleGroupItem>
            <ToggleGroupItem value="compact" aria-label={t('Compact')}>
                <AlignJustify />
            </ToggleGroupItem>
        </ToggleGroup>
    );
}
