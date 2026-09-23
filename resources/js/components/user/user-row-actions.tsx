import { router, usePage } from '@inertiajs/react';
import {
    Ban,
    KeyRound,
    MailPlus,
    MoreHorizontal,
    Pencil,
    Trash2,
    UserCheck,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCan } from '@/hooks/use-can';
import { invite, passwordReset, suspend } from '@/routes/users';
import type { Auth, User } from '@/types';

type Props = {
    user: User;
    onEdit: (user: User) => void;
    onDelete: (user: User) => void;
};

export function UserRowActions({ user, onEdit, onDelete }: Props) {
    const can = useCan();
    const { auth } = usePage<{ auth: Auth }>().props;

    const isTargetSuperAdmin = user.roles?.includes('Super Admin') ?? false;
    const isSelf = auth.user?.id === user.id;
    const actorIsSuperAdmin = auth.roles.includes('Super Admin');

    const canManage =
        can('user.update') && (!isTargetSuperAdmin || actorIsSuperAdmin);
    const canDelete = can('user.delete') && !isTargetSuperAdmin && !isSelf;
    const canEdit = canManage || isSelf;

    if (!canEdit && !canManage && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Actions for ${user.name}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                {canEdit && (
                    <DropdownMenuItem onSelect={() => onEdit(user)}>
                        <Pencil className="mr-2 size-4" />
                        Edit
                    </DropdownMenuItem>
                )}

                {canManage && user.status === 'invited' && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.post(
                                invite.url(user.id),
                                {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    >
                        <MailPlus className="mr-2 size-4" />
                        Resend invitation
                    </DropdownMenuItem>
                )}

                {canManage && user.status === 'active' && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.post(
                                passwordReset.url(user.id),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <KeyRound className="mr-2 size-4" />
                        Send password reset
                    </DropdownMenuItem>
                )}

                {canManage && !isSelf && (
                    <DropdownMenuItem
                        onSelect={() =>
                            router.post(
                                suspend.url(user.id),
                                {},
                                {
                                    preserveScroll: true,
                                },
                            )
                        }
                    >
                        {user.status === 'suspended' ? (
                            <>
                                <UserCheck className="mr-2 size-4" />
                                Reactivate
                            </>
                        ) : (
                            <>
                                <Ban className="mr-2 size-4" />
                                Suspend
                            </>
                        )}
                    </DropdownMenuItem>
                )}

                {canDelete && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => onDelete(user)}
                        >
                            <Trash2 className="mr-2 size-4" />
                            Delete
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
