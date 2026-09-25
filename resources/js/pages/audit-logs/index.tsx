import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Download, History } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table/data-table';
import { DataTableCursorPagination } from '@/components/app/data-table/data-table-cursor-pagination';
import { DataTableToolbar } from '@/components/app/data-table/data-table-toolbar';
import { PageHeader } from '@/components/app/page-header';
import { Combobox } from '@/components/app/combobox';
import { ExportDialog } from '@/components/data-processing/export-dialog';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    useDataTableFilters,
    type TableFilters,
} from '@/hooks/use-data-table-filters';
import { index as auditLogsIndex, prune } from '@/routes/audit-logs';
import type { AuditLog, AuditLogIndexProps } from '@/types';

type SelectOption = { value: string; label: string };

const channelVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    auth: 'secondary',
    security: 'destructive',
    rbac: 'default',
    settings: 'outline',
    domain: 'outline',
};

const eventVariant: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    created: 'default',
    updated: 'secondary',
    deleted: 'destructive',
    restored: 'default',
};

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function stringifyValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    if (typeof value === 'string') {
        return value;
    }

    return JSON.stringify(value);
}

export default function AuditLogsIndex({
    logs,
    filters,
    channels,
    events,
    canViewAll,
    canManage,
    canExport,
    processingOptions,
}: AuditLogIndexProps) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [channel, setChannel] = useState(filters.channel ?? 'all');
    const [event, setEvent] = useState(filters.event ?? 'all');
    const [selected, setSelected] = useState<AuditLog | null>(null);
    const [pruneOpen, setPruneOpen] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);

    const exportParameters = {
        search: search || undefined,
        channel: channel === 'all' ? undefined : channel,
        event: event === 'all' ? undefined : event,
        date_from: filters.date_from || undefined,
        date_to: filters.date_to || undefined,
    };

    const { apply } = useDataTableFilters(
        auditLogsIndex.url(),
        filters as TableFilters,
    );

    const columns: ColumnDef<AuditLog>[] = [
        {
            accessorKey: 'created_at',
            enableSorting: false,
            header: () => 'When',
            cell: ({ row }) => (
                <span className="text-sm whitespace-nowrap text-muted-foreground">
                    {formatDateTime(row.original.created_at)}
                </span>
            ),
        },
        {
            accessorKey: 'causer',
            enableSorting: false,
            header: () => 'User',
            cell: ({ row }) =>
                row.original.causer ? (
                    <div className="flex items-center gap-2">
                        <Avatar className="size-7">
                            <AvatarFallback className="text-xs">
                                {row.original.causer.name
                                    .split(' ')
                                    .map((part) => part[0])
                                    .slice(0, 2)
                                    .join('')
                                    .toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-medium">
                                {row.original.causer.name}
                            </p>
                        </div>
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">
                        System
                    </span>
                ),
        },
        {
            accessorKey: 'channel',
            enableSorting: false,
            header: () => 'Channel',
            cell: ({ row }) =>
                row.original.channel ? (
                    <Badge
                        variant={
                            channelVariant[row.original.channel] ?? 'outline'
                        }
                    >
                        {row.original.channel}
                    </Badge>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            accessorKey: 'event',
            enableSorting: false,
            header: () => 'Event',
            cell: ({ row }) =>
                row.original.event ? (
                    <Badge
                        variant={eventVariant[row.original.event] ?? 'outline'}
                    >
                        {row.original.event.replaceAll('_', ' ')}
                    </Badge>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            accessorKey: 'description',
            enableSorting: false,
            header: () => 'Description',
            cell: ({ row }) => (
                <span className="line-clamp-1 text-sm">
                    {row.original.description}
                </span>
            ),
        },
        {
            id: 'subject',
            enableSorting: false,
            header: () => 'Subject',
            cell: ({ row }) =>
                row.original.subject ? (
                    <span className="text-sm text-muted-foreground">
                        {row.original.subject.label}
                    </span>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
    ];

    const changes =
        selected?.attribute_changes?.attributes !== undefined ||
        selected?.attribute_changes?.old !== undefined
            ? {
                  attributes: selected?.attribute_changes?.attributes ?? {},
                  old: selected?.attribute_changes?.old ?? {},
              }
            : null;

    const changedKeys = changes
        ? Array.from(
              new Set([
                  ...Object.keys(changes.attributes),
                  ...Object.keys(changes.old),
              ]),
          )
        : [];

    const confirmPrune = () => {
        router.post(prune.url(), {}, { preserveScroll: true });
        setPruneOpen(false);
    };

    return (
        <>
            <Head title="Audit Log" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Audit Log"
                    description={
                        canViewAll
                            ? 'Everything users do across the workspace, newest first.'
                            : 'Your own recorded actions, newest first.'
                    }
                    actions={
                        <>
                            {canExport && (
                                <Button
                                    variant="outline"
                                    onClick={() => setExportOpen(true)}
                                >
                                    <Download />
                                    Export
                                </Button>
                            )}
                            {canManage && (
                                <Button
                                    variant="outline"
                                    onClick={() => setPruneOpen(true)}
                                >
                                    Prune entries
                                </Button>
                            )}
                        </>
                    }
                />

                <DataTableToolbar
                    searchValue={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        apply({ search: value, cursor: null });
                    }}
                    searchPlaceholder="Search descriptions…"
                >
                    <Select
                        value={channel}
                        onValueChange={(value) => {
                            setChannel(value);
                            apply(
                                {
                                    channel:
                                        value === 'all' ? undefined : value,
                                    cursor: null,
                                },
                                true,
                            );
                        }}
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All channels" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All channels</SelectItem>
                            {channels.map((option: SelectOption) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Combobox
                        className="w-44"
                        value={event}
                        onValueChange={(value) => {
                            setEvent(value);
                            apply(
                                {
                                    event: value === 'all' ? undefined : value,
                                    cursor: null,
                                },
                                true,
                            );
                        }}
                        options={[
                            { value: 'all', label: 'All events' },
                            ...events,
                        ]}
                        placeholder="All events"
                        searchPlaceholder="Search events…"
                    />
                </DataTableToolbar>

                <div className="space-y-4">
                    <DataTable
                        columns={columns}
                        data={logs.data}
                        onRowClick={(entry) => setSelected(entry)}
                        emptyState={{
                            icon: History,
                            title: 'No audit entries found',
                            description:
                                'Try adjusting your search or filters.',
                        }}
                    />

                    <DataTableCursorPagination
                        meta={logs.meta}
                        onPrevious={() =>
                            apply({ cursor: logs.meta.prev_cursor }, true)
                        }
                        onNext={() =>
                            apply({ cursor: logs.meta.next_cursor }, true)
                        }
                    />
                </div>
            </div>

            <Sheet
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <SheetContent className="overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>{selected?.description}</SheetTitle>
                        <SheetDescription>
                            {formatDateTime(selected?.created_at ?? null)}
                        </SheetDescription>
                    </SheetHeader>

                    {selected && (
                        <div className="space-y-6 px-4 pb-6">
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        User
                                    </dt>
                                    <dd className="text-right font-medium">
                                        {selected.causer
                                            ? `${selected.causer.name} (${selected.causer.email})`
                                            : 'System'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Channel
                                    </dt>
                                    <dd className="font-medium">
                                        {selected.channel ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Event
                                    </dt>
                                    <dd className="font-medium">
                                        {selected.event ?? '—'}
                                    </dd>
                                </div>
                                {selected.subject && (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            Subject
                                        </dt>
                                        <dd className="font-medium">
                                            {selected.subject.label} (
                                            {selected.subject.type
                                                .split('\\')
                                                .pop()}
                                            #{selected.subject.id})
                                        </dd>
                                    </div>
                                )}
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        IP address
                                    </dt>
                                    <dd className="font-medium">
                                        {typeof selected.properties
                                            .ip_address === 'string'
                                            ? selected.properties.ip_address
                                            : '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-4">
                                    <dt className="text-muted-foreground">
                                        Correlation ID
                                    </dt>
                                    <dd className="max-w-48 truncate font-mono text-xs">
                                        {selected.correlation_id ?? '—'}
                                    </dd>
                                </div>
                            </dl>

                            {changedKeys.length > 0 && (
                                <div>
                                    <h4 className="mb-2 text-sm font-medium">
                                        Changes
                                    </h4>
                                    <div className="overflow-hidden rounded-md border">
                                        {changedKeys.map((key) => (
                                            <div
                                                key={key}
                                                className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 border-b px-3 py-2 text-sm last:border-b-0"
                                            >
                                                <span className="truncate rounded-sm bg-destructive/10 px-2 py-1 font-mono text-xs text-destructive">
                                                    {stringifyValue(
                                                        changes?.old[key],
                                                    )}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    →
                                                </span>
                                                <span className="truncate rounded-sm bg-emerald-500/10 px-2 py-1 font-mono text-xs text-emerald-600 dark:text-emerald-400">
                                                    {stringifyValue(
                                                        changes?.attributes[
                                                            key
                                                        ],
                                                    )}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        {changedKeys.join(', ')}
                                    </p>
                                </div>
                            )}
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            <ConfirmDialog
                open={pruneOpen}
                onOpenChange={setPruneOpen}
                title="Prune audit log?"
                description="Entries older than each channel's retention window will be permanently deleted. This cannot be undone."
                confirmLabel="Prune"
                destructive
                onConfirm={confirmPrune}
            />

            <ExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                options={processingOptions}
                lockEntity="audit-logs"
                parameters={exportParameters}
            />
        </>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Audit Log', href: auditLogsIndex() }],
};
