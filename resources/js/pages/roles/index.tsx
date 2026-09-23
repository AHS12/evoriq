import { Head, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Plus, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table/data-table';
import { DataTableColumnHeader } from '@/components/app/data-table/data-table-column-header';
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/app/data-table/data-table-toolbar';
import { PageHeader } from '@/components/app/page-header';
import { RoleFormDialog } from '@/components/role/role-form-dialog';
import { RoleRowActions } from '@/components/role/role-row-actions';
import { SystemRoleBadge } from '@/components/role/system-role-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import {
    useDataTableFilters,
    type TableFilters,
} from '@/hooks/use-data-table-filters';
import { destroy, index } from '@/routes/roles';
import type { Paginated, Permission, Role } from '@/types';

type Filters = {
    search?: string | null;
    order_by?: string | null;
    order_direction?: string | null;
    page?: string | number | null;
    per_page?: string | number | null;
};

type Props = {
    roles: Paginated<Role>;
    filters: Filters;
    permissions: Permission[];
};

function formatDate(value?: string): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function RolesIndex({ roles, filters, permissions }: Props) {
    const can = useCan();

    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingDelete, setPendingDelete] = useState<Role | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editingRole, setEditingRole] = useState<Role | null>(null);

    const openCreate = () => {
        setEditingRole(null);
        setFormOpen(true);
    };

    const openEdit = (role: Role) => {
        setEditingRole(role);
        setFormOpen(true);
    };

    const { apply } = useDataTableFilters(index.url(), filters as TableFilters);

    const sorting: SortingState = filters.order_by
        ? [{ id: filters.order_by, desc: filters.order_direction === 'desc' }]
        : [];

    const handleSortingChange = (next: SortingState) => {
        const [first] = next;

        apply(
            {
                order_by: first?.id ?? 'name',
                order_direction: first?.desc ? 'desc' : 'asc',
                page: 1,
            },
            true,
        );
    };

    const columns: ColumnDef<Role>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Role" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-2">
                    <span className="font-medium">{row.original.name}</span>
                    {row.original.is_system && <SystemRoleBadge />}
                </div>
            ),
        },
        {
            accessorKey: 'permissions_count',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Permissions" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.permissions_count ?? 0}
                </span>
            ),
        },
        {
            accessorKey: 'users_count',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Users" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {row.original.users_count ?? 0}
                </span>
            ),
        },
        {
            accessorKey: 'created_at',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Created" />
            ),
            cell: ({ row }) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.original.created_at)}
                </span>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: () => <span className="sr-only">Actions</span>,
            cell: ({ row }) => (
                <div className="flex justify-end">
                    <RoleRowActions
                        role={row.original}
                        onEdit={openEdit}
                        onDelete={setPendingDelete}
                    />
                </div>
            ),
        },
    ];

    const confirmDelete = () => {
        if (!pendingDelete) {
            return;
        }

        router.delete(destroy.url(pendingDelete.id), {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    };

    const emptyAction = can('role.create') ? (
        <Button onClick={openCreate}>
            <Plus />
            New role
        </Button>
    ) : undefined;

    return (
        <>
            <Head title="Roles & Permissions" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Roles & Permissions"
                    description="Define roles and choose which permissions each one grants."
                    actions={
                        can('role.create') ? (
                            <Button onClick={openCreate}>
                                <Plus />
                                New role
                            </Button>
                        ) : undefined
                    }
                />

                <DataTableToolbar
                    searchValue={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        apply({ search: value, page: 1 });
                    }}
                    searchPlaceholder="Search roles…"
                />

                <div className="space-y-4">
                    <DataTable
                        columns={columns}
                        data={roles.data}
                        sorting={sorting}
                        onSortingChange={handleSortingChange}
                        emptyState={{
                            icon: ShieldCheck,
                            title: 'No roles found',
                            description:
                                'Try adjusting your search, or create a custom role.',
                            action: emptyAction,
                        }}
                    />

                    <DataTablePagination
                        meta={roles.meta}
                        onPageChange={(page) => apply({ page }, true)}
                    />
                </div>
            </div>

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                    }
                }}
                title="Delete role?"
                description={
                    pendingDelete
                        ? `${pendingDelete.name} will be permanently deleted. Users assigned to it will lose these permissions.`
                        : undefined
                }
                confirmLabel="Delete"
                destructive
                onConfirm={confirmDelete}
            />

            <RoleFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                permissions={permissions}
                role={editingRole ?? undefined}
            />
        </>
    );
}
