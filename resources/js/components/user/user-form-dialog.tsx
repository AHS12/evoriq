import { UserForm } from '@/components/user/user-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { User, UserStatus } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roles: string[];
    statuses?: { value: UserStatus; label: string }[];
    user?: User;
};

export function UserFormDialog({
    open,
    onOpenChange,
    roles,
    statuses,
    user,
}: Props) {
    const isEditing = Boolean(user);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit user' : 'New user'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing
                            ? "Update this user's details, roles and status."
                            : 'Invite someone to the workspace. They will receive an email to set their password.'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <UserForm
                        key={user?.id ?? 'create'}
                        roles={roles}
                        statuses={statuses}
                        user={user}
                        onCancel={() => onOpenChange(false)}
                        onSuccess={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
