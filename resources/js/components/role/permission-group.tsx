import { ChevronRight, Lock } from 'lucide-react';
import { useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { Permission } from '@/types';

type Props = {
    group: string;
    permissions: Permission[];
    value: string[];
    onChange: (value: string[]) => void;
    readOnly?: boolean;
    allowSystemPermissions?: boolean;
};

export function PermissionGroup({
    group,
    permissions,
    value,
    onChange,
    readOnly = false,
    allowSystemPermissions = false,
}: Props) {
    const [open, setOpen] = useState(true);

    const assignableNames = permissions
        .filter((permission) => allowSystemPermissions || !permission.is_system)
        .map((permission) => permission.name);

    const selected = assignableNames.filter((name) => value.includes(name));
    const allSelected =
        assignableNames.length > 0 &&
        selected.length === assignableNames.length;
    const someSelected = selected.length > 0 && !allSelected;

    const toggleGroup = (checked: boolean) => {
        if (checked) {
            onChange([...new Set([...value, ...assignableNames])]);

            return;
        }

        onChange(value.filter((name) => !assignableNames.includes(name)));
    };

    const togglePermission = (name: string, checked: boolean) => {
        onChange(
            checked
                ? [...new Set([...value, name])]
                : value.filter((current) => current !== name),
        );
    };

    const groupCheckboxId = `permission-group-${group}`;

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="overflow-hidden rounded-lg border"
        >
            <div className="flex items-center justify-between gap-3 bg-muted/40 px-3 py-2">
                <div className="flex items-center gap-2.5">
                    {!readOnly && (
                        <Checkbox
                            id={groupCheckboxId}
                            checked={
                                allSelected
                                    ? true
                                    : someSelected
                                      ? 'indeterminate'
                                      : false
                            }
                            onCheckedChange={(checked) =>
                                toggleGroup(checked === true)
                            }
                        />
                    )}
                    <CollapsibleTrigger className="flex items-center gap-1.5 text-sm font-medium capitalize">
                        <ChevronRight
                            className={cn(
                                'size-4 transition-transform',
                                open && 'rotate-90',
                            )}
                        />
                        {group}
                    </CollapsibleTrigger>
                </div>
                <span className="text-xs text-muted-foreground">
                    {selected.length}/{assignableNames.length}
                </span>
            </div>

            <CollapsibleContent>
                <div className="grid gap-0.5 border-t p-2 sm:grid-cols-2 lg:grid-cols-3">
                    {permissions.map((permission) => {
                        const locked =
                            readOnly ||
                            (permission.is_system && !allowSystemPermissions);

                        return (
                            <div
                                key={permission.name}
                                className={cn(
                                    'flex items-start gap-2.5 rounded-md p-2',
                                    !locked && 'hover:bg-accent',
                                )}
                            >
                                <Checkbox
                                    id={`permission-${permission.name}`}
                                    checked={value.includes(permission.name)}
                                    disabled={locked}
                                    onCheckedChange={(checked) =>
                                        togglePermission(
                                            permission.name,
                                            checked === true,
                                        )
                                    }
                                    className="mt-0.5"
                                />
                                <label
                                    htmlFor={`permission-${permission.name}`}
                                    className={cn(
                                        'min-w-0 select-none',
                                        !locked
                                            ? 'cursor-pointer'
                                            : 'cursor-not-allowed opacity-60',
                                    )}
                                >
                                    <span className="flex items-center gap-1.5 text-sm font-medium">
                                        {permission.name}
                                        {permission.is_system && (
                                            <Lock className="size-3 text-muted-foreground" />
                                        )}
                                    </span>
                                    {permission.description && (
                                        <span className="block text-xs text-muted-foreground">
                                            {permission.description}
                                        </span>
                                    )}
                                </label>
                            </div>
                        );
                    })}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
