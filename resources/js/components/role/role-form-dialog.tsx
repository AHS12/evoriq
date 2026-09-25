import { RoleForm } from '@/components/role/role-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import type { Permission, Role } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    permissions: Permission[];
    role?: Role;
};

export function RoleFormDialog({
    open,
    onOpenChange,
    permissions,
    role,
}: Props) {
    const isEditing = Boolean(role);
    const isSuperAdmin = role?.name === 'Super Admin';
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-4xl">
                <DialogHeader className="shrink-0 border-b px-6 py-4">
                    <DialogTitle>
                        {isSuperAdmin
                            ? t('Super Admin role')
                            : isEditing
                              ? t('Edit role')
                              : t('New role')}
                    </DialogTitle>
                    <DialogDescription>
                        {isSuperAdmin
                            ? t(
                                  'The Super Admin role is locked and always holds every permission.',
                              )
                            : isEditing
                              ? t(
                                    'Update the role name and the permissions it grants.',
                                )
                              : t(
                                    'Create a custom role and choose the permissions it grants.',
                                )}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <RoleForm
                        key={role?.id ?? 'create'}
                        permissions={permissions}
                        role={role}
                        onCancel={() => onOpenChange(false)}
                        onSuccess={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
