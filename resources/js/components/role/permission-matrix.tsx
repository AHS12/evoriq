import { useMemo } from 'react';
import { PermissionGroup } from '@/components/role/permission-group';
import { Button } from '@/components/ui/button';
import type { Permission } from '@/types';

type Props = {
    permissions: Permission[];
    value: string[];
    onChange: (value: string[]) => void;
    readOnly?: boolean;
    allowSystemPermissions?: boolean;
};

export function PermissionMatrix({
    permissions,
    value,
    onChange,
    readOnly = false,
    allowSystemPermissions = false,
}: Props) {
    const groups = useMemo(() => {
        const map = new Map<string, Permission[]>();

        for (const permission of permissions) {
            const key = permission.group ?? 'other';
            const items = map.get(key) ?? [];
            items.push(permission);
            map.set(key, items);
        }

        return [...map.entries()].map(([group, items]) => ({
            group,
            permissions: items,
        }));
    }, [permissions]);

    const assignableNames = useMemo(
        () =>
            permissions
                .filter(
                    (permission) =>
                        allowSystemPermissions || !permission.is_system,
                )
                .map((permission) => permission.name),
        [permissions, allowSystemPermissions],
    );

    return (
        <div className="space-y-3">
            {!readOnly && (
                <div className="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            onChange([
                                ...new Set([...value, ...assignableNames]),
                            ])
                        }
                    >
                        Select all
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            onChange(
                                value.filter(
                                    (name) => !assignableNames.includes(name),
                                ),
                            )
                        }
                    >
                        Clear
                    </Button>
                </div>
            )}

            <div className="space-y-2">
                {groups.map(({ group, permissions: items }) => (
                    <PermissionGroup
                        key={group}
                        group={group}
                        permissions={items}
                        value={value}
                        onChange={onChange}
                        readOnly={readOnly}
                        allowSystemPermissions={allowSystemPermissions}
                    />
                ))}
            </div>
        </div>
    );
}
