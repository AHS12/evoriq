import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const PER_PAGE_OPTIONS = [10, 15, 25, 50, 100];

type Props = {
    value: number;
    onChange: (perPage: number) => void;
    disabled?: boolean;
};

export function DataTablePerPage({ value, onChange, disabled }: Props) {
    return (
        <div className="flex items-center gap-2">
            <span className="hidden text-sm text-muted-foreground sm:inline">
                Per page
            </span>
            <Select
                value={String(value)}
                onValueChange={(next) => onChange(Number(next))}
                disabled={disabled}
            >
                <SelectTrigger className="w-18" aria-label="Rows per page">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {PER_PAGE_OPTIONS.map((option) => (
                        <SelectItem key={option} value={String(option)}>
                            {option}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
