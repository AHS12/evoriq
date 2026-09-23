import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Props = {
    roles: string[];
    value: string[];
    onChange: (value: string[]) => void;
    disabled?: boolean;
    id?: string;
    placeholder?: string;
};

export function RoleSelect({
    roles,
    value,
    onChange,
    disabled = false,
    id,
    placeholder = 'Select roles',
}: Props) {
    const toggle = (role: string) => {
        onChange(
            value.includes(role)
                ? value.filter((selected) => selected !== role)
                : [...value, role],
        );
    };

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    disabled={disabled}
                    className="w-full justify-between font-normal"
                >
                    <span
                        className={cn(
                            'truncate',
                            value.length === 0 && 'text-muted-foreground',
                        )}
                    >
                        {value.length > 0 ? value.join(', ') : placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-(--radix-popover-trigger-width) p-1"
            >
                <div className="flex flex-col">
                    {roles.map((role) => {
                        const selected = value.includes(role);

                        return (
                            <button
                                key={role}
                                type="button"
                                onClick={() => toggle(role)}
                                className="flex items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm outline-none hover:bg-accent hover:text-accent-foreground"
                            >
                                <span
                                    className={cn(
                                        'flex size-4 items-center justify-center rounded-sm border',
                                        selected &&
                                            'border-primary bg-primary text-primary-foreground',
                                    )}
                                >
                                    {selected && <Check className="size-3" />}
                                </span>
                                {role}
                            </button>
                        );
                    })}

                    {roles.length === 0 && (
                        <p className="px-2 py-3 text-sm text-muted-foreground">
                            No roles available.
                        </p>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}
