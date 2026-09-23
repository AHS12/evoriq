import { Lock, MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCan } from '@/hooks/use-can';
import type { Role } from '@/types';

type Props = {
    role: Role;
    onEdit: (role: Role) => void;
    onDelete: (role: Role) => void;
};

export function RoleRowActions({ role, onEdit, onDelete }: Props) {
    const can = useCan();

    const isSuperAdmin = role.name === 'Super Admin';
    const canUpdate = can('role.update');
    const canDelete = can('role.delete') && !role.is_system;

    if (!canUpdate && !canDelete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Actions for ${role.name}`}
                >
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                {canUpdate && (
                    <DropdownMenuItem onSelect={() => onEdit(role)}>
                        {isSuperAdmin ? (
                            <Lock className="mr-2 size-4" />
                        ) : (
                            <Pencil className="mr-2 size-4" />
                        )}
                        {isSuperAdmin ? 'View' : 'Edit'}
                    </DropdownMenuItem>
                )}

                {canDelete && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={() => onDelete(role)}
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
