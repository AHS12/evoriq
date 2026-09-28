import { Head, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Plus, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table/data-table';
import { DataTableColumnHeader } from '@/components/app/data-table/data-table-column-header';
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/app/data-table/data-table-toolbar';
import { DataTableViewControls } from '@/components/app/data-table/data-table-view-controls';
import { PageHeader } from '@/components/app/page-header';
import { RoleFormDialog } from '@/components/role/role-form-dialog';
import { RoleDetailsSheet } from '@/components/role/role-details-sheet';
import { RoleRowActions } from '@/components/role/role-row-actions';
import { SystemRoleBadge } from '@/components/role/system-role-badge';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import {
    useDataTableFilters,
    type TableFilters,
} from '@/hooks/use-data-table-filters';
import { useTablePreferences } from '@/hooks/use-table-preferences';
import { useTranslation } from '@/hooks/use-translation';
import { destroy, index } from '@/routes/roles';
import type { Paginated, Permission, Role } from '@/types';
import { formatDate } from '@/lib/format';

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

export default function RolesIndex({ roles, filters, permissions }: Props) {
    const can = useCan();
    const { t } = useTranslation();

    const [search, setSearch] = useState(filters.search ?? '');
    const [pendingDelete, setPendingDelete] = useState<Role | null>(null);
    const [detailsRole, setDetailsRole] = useState<Role | null>(null);
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

    const { apply, replace, isLoading } = useDataTableFilters(
        index.url(),
        filters as TableFilters,
    );

    const tablePreferences = useTablePreferences('roles');

    const savedViewFilters = {
        search: search || undefined,
        order_by: filters.order_by ?? undefined,
        order_direction: filters.order_direction ?? undefined,
    };

    const applySavedView = (
        next: Record<string, string | number | boolean | null | undefined>,
    ) => {
        setSearch(typeof next.search === 'string' ? next.search : '');
        replace(next);
    };

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
            meta: { label: t('Role') },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Role')} />
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
            meta: { label: t('Permissions') },
            header: ({ column }) => (
                <DataTableColumnHeader
                    column={column}
                    title={t('Permissions')}
                />
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
            meta: { label: t('Users') },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Users')} />
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
            meta: { label: t('Created') },
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title={t('Created')} />
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
            enableHiding: false,
            header: () => <span className="sr-only">{t('Actions')}</span>,
            cell: ({ row }) => (
                <div
                    className="flex justify-end"
                    onClick={(event) => event.stopPropagation()}
                >
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
            {t('New role')}
        </Button>
    ) : undefined;

    return (
        <>
            <Head title={t('Roles & Permissions')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={t('Roles & Permissions')}
                    description={t(
                        'Define roles and choose which permissions each one grants.',
                    )}
                    actions={
                        can('role.create') ? (
                            <Button onClick={openCreate}>
                                <Plus />
                                {t('New role')}
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
                    searchPlaceholder={t('Search roles…')}
                >
                    <DataTableViewControls
                        preferences={tablePreferences}
                        columns={columns}
                        filters={savedViewFilters}
                        onApplyView={applySavedView}
                        disabled={isLoading}
                    />
                </DataTableToolbar>

                <div className="space-y-4">
                    <DataTable
                        columns={columns}
                        data={roles.data}
                        isLoading={isLoading}
                        sorting={sorting}
                        onSortingChange={handleSortingChange}
                        onRowClick={setDetailsRole}
                        density={tablePreferences.density}
                        columnVisibility={tablePreferences.columnVisibility}
                        onColumnVisibilityChange={
                            tablePreferences.setColumnVisibility
                        }
                        stickyHeader={tablePreferences.stickyHeader}
                        emptyState={{
                            icon: ShieldCheck,
                            title: t('No roles found'),
                            description: t(
                                'Try adjusting your search, or create a custom role.',
                            ),
                            action: emptyAction,
                        }}
                    />

                    <DataTablePagination
                        meta={roles.meta}
                        disabled={isLoading}
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
                title={t('Delete role?')}
                description={
                    pendingDelete
                        ? t(
                              ':name will be permanently deleted. Users assigned to it will lose these permissions.',
                              { name: pendingDelete.name },
                          )
                        : undefined
                }
                confirmLabel={t('Delete')}
                destructive
                onConfirm={confirmDelete}
            />

            <RoleDetailsSheet
                role={detailsRole}
                permissions={permissions}
                onOpenChange={(open) => {
                    if (!open) {
                        setDetailsRole(null);
                    }
                }}
                onEdit={(role) => {
                    setDetailsRole(null);
                    openEdit(role);
                }}
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
