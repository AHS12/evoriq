import { Head, router } from '@inertiajs/react';
import type { ColumnDef, SortingState } from '@tanstack/react-table';
import { Download, Plus, Upload, Users } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table/data-table';
import { DataTableColumnHeader } from '@/components/app/data-table/data-table-column-header';
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/app/data-table/data-table-toolbar';
import { PageHeader } from '@/components/app/page-header';
import { ExportDialog } from '@/components/data-processing/export-dialog';
import { ImportDialog } from '@/components/data-processing/import-dialog';
import { UserFormDialog } from '@/components/user/user-form-dialog';
import { UserRowActions } from '@/components/user/user-row-actions';
import { UserStatusBadge } from '@/components/user/user-status-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useCan } from '@/hooks/use-can';
import {
    useDataTableFilters,
    type TableFilters,
} from '@/hooks/use-data-table-filters';
import { useInitials } from '@/hooks/use-initials';
import { destroy, index } from '@/routes/users';
import type { JobOptions, Paginated, User, UserStatus } from '@/types';

type Filters = {
    search?: string | null;
    role?: string | null;
    status?: string | null;
    order_by?: string | null;
    order_direction?: string | null;
    page?: string | number | null;
    per_page?: string | number | null;
};

type Props = {
    users: Paginated<User>;
    filters: Filters;
    roles: string[];
    statuses: { value: UserStatus; label: string }[];
    processingOptions: JobOptions;
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export default function UsersIndex({
    users,
    filters,
    roles,
    statuses,
    processingOptions,
}: Props) {
    const can = useCan();
    const getInitials = useInitials();

    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? 'all');
    const [status, setStatus] = useState(filters.status ?? 'all');
    const [pendingDelete, setPendingDelete] = useState<User | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [exportOpen, setExportOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);

    const openCreate = () => {
        setEditingUser(null);
        setFormOpen(true);
    };

    const openEdit = (user: User) => {
        setEditingUser(user);
        setFormOpen(true);
    };

    const { apply } = useDataTableFilters(index.url(), filters as TableFilters);

    const sorting: SortingState = filters.order_by
        ? [{ id: filters.order_by, desc: filters.order_direction !== 'asc' }]
        : [];

    const handleSortingChange = (next: SortingState) => {
        const [first] = next;

        apply(
            {
                order_by: first?.id ?? 'created_at',
                order_direction: first?.desc ? 'desc' : 'asc',
                page: 1,
            },
            true,
        );
    };

    const columns: ColumnDef<User>[] = [
        {
            accessorKey: 'name',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="User" />
            ),
            cell: ({ row }) => (
                <div className="flex items-center gap-3">
                    <Avatar className="size-9">
                        <AvatarFallback>
                            {getInitials(row.original.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <p className="truncate font-medium">
                            {row.original.name}
                        </p>
                        <p className="truncate text-sm text-muted-foreground">
                            {row.original.email}
                        </p>
                    </div>
                </div>
            ),
        },
        {
            accessorKey: 'roles',
            enableSorting: false,
            header: () => 'Roles',
            cell: ({ row }) =>
                row.original.roles && row.original.roles.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {row.original.roles.map((name) => (
                            <Badge key={name} variant="outline">
                                {name}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            accessorKey: 'status',
            enableSorting: true,
            header: ({ column }) => (
                <DataTableColumnHeader column={column} title="Status" />
            ),
            cell: ({ row }) => (
                <UserStatusBadge
                    status={row.original.status}
                    label={row.original.status_label}
                />
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
                    <UserRowActions
                        user={row.original}
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

    const emptyAction = can('user.create') ? (
        <Button onClick={openCreate}>
            <Plus />
            New user
        </Button>
    ) : undefined;

    return (
        <>
            <Head title="Users" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Users"
                    description="Manage who can access the workspace and what they can do."
                    actions={
                        <>
                            {can('user.import') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setImportOpen(true)}
                                >
                                    <Upload />
                                    Import
                                </Button>
                            )}
                            {can('user.export') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setExportOpen(true)}
                                >
                                    <Download />
                                    Export
                                </Button>
                            )}
                            {can('user.create') && (
                                <Button onClick={openCreate}>
                                    <Plus />
                                    New user
                                </Button>
                            )}
                        </>
                    }
                />

                <DataTableToolbar
                    searchValue={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        apply({ search: value, page: 1 });
                    }}
                    searchPlaceholder="Search by name or email…"
                >
                    <Select
                        value={role}
                        onValueChange={(value) => {
                            setRole(value);
                            apply(
                                {
                                    role: value === 'all' ? undefined : value,
                                    page: 1,
                                },
                                true,
                            );
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All roles" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All roles</SelectItem>
                            {roles.map((name) => (
                                <SelectItem key={name} value={name}>
                                    {name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={status}
                        onValueChange={(value) => {
                            setStatus(value);
                            apply(
                                {
                                    status: value === 'all' ? undefined : value,
                                    page: 1,
                                },
                                true,
                            );
                        }}
                    >
                        <SelectTrigger className="w-40">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </DataTableToolbar>

                <div className="space-y-4">
                    <DataTable
                        columns={columns}
                        data={users.data}
                        sorting={sorting}
                        onSortingChange={handleSortingChange}
                        emptyState={{
                            icon: Users,
                            title: 'No users found',
                            description:
                                'Try adjusting your search or filters, or invite a new user.',
                            action: emptyAction,
                        }}
                    />

                    <DataTablePagination
                        meta={users.meta}
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
                title="Delete user?"
                description={
                    pendingDelete
                        ? `${pendingDelete.name} will be permanently deleted. This cannot be undone.`
                        : undefined
                }
                confirmLabel="Delete"
                destructive
                onConfirm={confirmDelete}
            />

            <UserFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                roles={roles}
                statuses={statuses}
                user={editingUser ?? undefined}
            />

            <ExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                options={processingOptions}
                lockEntity="users"
                parameters={{
                    search: search || undefined,
                    status: status === 'all' ? undefined : status,
                    role: role === 'all' ? undefined : role,
                }}
            />

            <ImportDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                options={processingOptions}
                lockEntity="users"
            />
        </>
    );
}
