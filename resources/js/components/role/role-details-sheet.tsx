import { Pencil, ShieldCheck, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { SystemRoleBadge } from '@/components/role/system-role-badge';
import { useTranslation } from '@/hooks/use-translation';
import type { Permission, Role } from '@/types';
import { formatDate } from '@/lib/format';

type Props = {
    role: Role | null;
    permissions: Permission[];
    onOpenChange: (open: boolean) => void;
    onEdit?: (role: Role) => void;
};

export function RoleDetailsSheet({
    role,
    permissions,
    onOpenChange,
    onEdit,
}: Props) {
    const { t } = useTranslation();

    const groups = new Map<string, Permission[]>();
    for (const permission of permissions) {
        if (role?.permissions?.includes(permission.name)) {
            const group = permission.group ?? '';
            const existing = groups.get(group) ?? [];
            existing.push(permission);
            groups.set(group, existing);
        }
    }

    return (
        <Sheet open={role !== null} onOpenChange={onOpenChange}>
            <SheetContent className="flex flex-col gap-0 overflow-y-auto sm:max-w-md">
                {role && (
                    <>
                        <SheetHeader>
                            <div className="flex items-center gap-2">
                                <SheetTitle className="truncate">
                                    {role.name}
                                </SheetTitle>
                                {role.is_system && <SystemRoleBadge />}
                            </div>
                            <SheetDescription>
                                {t('Role details')}
                            </SheetDescription>
                        </SheetHeader>

                        <div className="flex-1 space-y-6 px-4 pb-6">
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-lg border p-3">
                                    <p className="text-2xl font-semibold">
                                        {role.permissions?.length ?? 0}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Permissions')}
                                    </p>
                                </div>
                                <div className="rounded-lg border p-3">
                                    <p className="text-2xl font-semibold">
                                        {role.users_count ?? 0}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {t('Users')}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-3">
                                <p className="flex items-center gap-2 text-sm font-medium">
                                    <ShieldCheck className="size-4 text-muted-foreground" />
                                    {t('Permissions')}
                                </p>
                                {groups.size > 0 ? (
                                    <div className="space-y-4">
                                        {[...groups.entries()].map(
                                            ([group, groupPermissions]) => (
                                                <div
                                                    key={group}
                                                    className="space-y-1.5"
                                                >
                                                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                        {group || t('Other')}
                                                    </p>
                                                    <div className="flex flex-wrap gap-1">
                                                        {groupPermissions.map(
                                                            (permission) => (
                                                                <Badge
                                                                    key={
                                                                        permission.id
                                                                    }
                                                                    variant="outline"
                                                                >
                                                                    {
                                                                        permission.name
                                                                    }
                                                                </Badge>
                                                            ),
                                                        )}
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('This role has no permissions yet.')}
                                    </p>
                                )}
                            </div>

                            <Separator />

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm text-muted-foreground">
                                    {t('Created')}
                                </span>
                                <span className="text-sm font-medium">
                                    {formatDate(role.created_at, {
                                        month: 'long',
                                    })}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Users className="size-4" />
                                    {t('Users with this role')}
                                </span>
                                <span className="text-sm font-medium">
                                    {role.users_count ?? 0}
                                </span>
                            </div>
                        </div>

                        {onEdit && (
                            <SheetFooter>
                                <Button onClick={() => onEdit(role)}>
                                    <Pencil />
                                    {t('Edit role')}
                                </Button>
                            </SheetFooter>
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
