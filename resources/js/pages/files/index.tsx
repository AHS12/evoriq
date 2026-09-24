import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    Download,
    Eye,
    FolderOpen,
    LayoutGrid,
    List,
    Search,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { FilePreviewDialog } from '@/components/file/file-preview-dialog';
import { FileThumbnail } from '@/components/file/file-thumbnail';
import { formatSize } from '@/components/file/file-utils';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { index as activityIndex } from '@/routes/activity';
import { destroy, index, store } from '@/routes/files';
import type { FileEntry, Paginated } from '@/types';

type Props = {
    source: 'all' | 'generated';
    files: Paginated<FileEntry>;
    filters: {
        search?: string | null;
        type?: string | null;
    };
    types: {
        value: string;
        label: string;
    }[];
    canViewGenerated: boolean;
};

function fileName(entry: FileEntry): string {
    return entry.name || entry.file_name || 'Untitled file';
}

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

function SourceBadge({ entry }: { entry: FileEntry }) {
    if (entry.is_generated) {
        return (
            <Badge variant="secondary" className="gap-1">
                <Sparkles className="size-3" />
                System
            </Badge>
        );
    }

    return <Badge variant="outline">Uploaded</Badge>;
}

export default function FilesIndex({
    source,
    files,
    filters,
    types,
    canViewGenerated,
}: Props) {
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? 'all');
    const [pendingDelete, setPendingDelete] = useState<FileEntry | null>(null);
    const [previewFile, setPreviewFile] = useState<FileEntry | null>(null);
    const isFirstRender = useRef(true);

    const upload = useForm<{ file: File | null }>({ file: null });

    const navigate = (
        params: Record<string, string | number | undefined>,
    ): void => {
        router.get(
            index.url(),
            {
                source,
                search: search || undefined,
                type: type === 'all' ? undefined : type,
                ...params,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            navigate({ page: 1 });
        }, 300);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, type]);

    const submitFile = (file: File | null): void => {
        if (!file) {
            return;
        }

        upload.setData('file', file);
        upload.post(store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => upload.reset(),
        });
    };

    const confirmDelete = (): void => {
        if (!pendingDelete) {
            return;
        }

        router.delete(destroy.url(pendingDelete.id), {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    };

    const rows = files.data;

    return (
        <>
            <Head title="Files" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Files"
                    description="Everything stored here — your uploads and system-generated files."
                />

                <Tabs
                    value={source}
                    onValueChange={(value) =>
                        navigate({ source: value, page: 1 })
                    }
                >
                    <TabsList>
                        <TabsTrigger value="all">All files</TabsTrigger>
                        {canViewGenerated && (
                            <TabsTrigger value="generated">
                                Generated
                            </TabsTrigger>
                        )}
                    </TabsList>
                </Tabs>

                {source === 'all' && (
                    <Card>
                        <CardContent>
                            <label
                                htmlFor="file-upload"
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={(event) => {
                                    event.preventDefault();
                                    submitFile(
                                        event.dataTransfer.files?.[0] ?? null,
                                    );
                                }}
                                className="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-center transition-colors hover:border-primary/50"
                            >
                                <div className="flex size-10 items-center justify-center rounded-full bg-muted">
                                    {upload.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Upload className="size-5 text-muted-foreground" />
                                    )}
                                </div>
                                <p className="text-sm font-medium">
                                    Drag &amp; drop a file here, or click to
                                    browse
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {upload.progress
                                        ? `Uploading… ${upload.progress.percentage}%`
                                        : 'PDF, images, documents and more'}
                                </p>
                                <input
                                    id="file-upload"
                                    type="file"
                                    className="sr-only"
                                    onChange={(event) =>
                                        submitFile(
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </label>
                            <InputError
                                className="mt-2"
                                message={upload.errors.file}
                            />
                        </CardContent>
                    </Card>
                )}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search files…"
                            className="pl-9"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger className="w-40">
                                <SelectValue placeholder="All types" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All types</SelectItem>
                                {types.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={() =>
                                setView(view === 'grid' ? 'list' : 'grid')
                            }
                            aria-label="Toggle view"
                        >
                            {view === 'grid' ? (
                                <List className="size-4" />
                            ) : (
                                <LayoutGrid className="size-4" />
                            )}
                        </Button>
                    </div>
                </div>

                {rows.length > 0 ? (
                    <>
                        {view === 'grid' ? (
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                {rows.map((entry) => (
                                    <Card key={`${entry.source}-${entry.id}`}>
                                        <CardContent className="space-y-3">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    entry.source === 'upload'
                                                        ? setPreviewFile(entry)
                                                        : undefined
                                                }
                                                disabled={
                                                    entry.source !== 'upload'
                                                }
                                                className="relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-md bg-muted disabled:cursor-default"
                                                aria-label={`Preview ${fileName(entry)}`}
                                            >
                                                <FileThumbnail
                                                    name={fileName(entry)}
                                                    mimeType={entry.mime_type}
                                                    thumbUrl={entry.thumb_url}
                                                    url={entry.url}
                                                />
                                                <span className="absolute top-2 left-2">
                                                    <SourceBadge
                                                        entry={entry}
                                                    />
                                                </span>
                                            </button>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {fileName(entry)}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatSize(entry.size)} ·{' '}
                                                    {entry.is_generated
                                                        ? (entry.job_type_label ??
                                                          'Generated')
                                                        : entry.type_label}{' '}
                                                    ·{' '}
                                                    {formatDate(
                                                        entry.created_at,
                                                    )}
                                                </p>
                                            </div>
                                            <div className="flex items-center justify-between">
                                                <SourceBadge entry={entry} />
                                                <div className="flex items-center gap-1">
                                                    {entry.source ===
                                                        'upload' && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Preview file"
                                                            onClick={() =>
                                                                setPreviewFile(
                                                                    entry,
                                                                )
                                                            }
                                                        >
                                                            <Eye className="size-4" />
                                                        </Button>
                                                    )}
                                                    {entry.is_generated &&
                                                        entry.job_id && (
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="View job"
                                                            >
                                                                <a
                                                                    href={activityIndex.url(
                                                                        {
                                                                            query: {
                                                                                job_id: entry.job_id,
                                                                            },
                                                                        },
                                                                    )}
                                                                >
                                                                    <ArrowUpRight className="size-4" />
                                                                </a>
                                                            </Button>
                                                        )}
                                                    {entry.download_url && (
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Download"
                                                        >
                                                            <a
                                                                href={
                                                                    entry.download_url
                                                                }
                                                            >
                                                                <Download className="size-4" />
                                                            </a>
                                                        </Button>
                                                    )}
                                                    {entry.source ===
                                                        'upload' && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label="Delete file"
                                                            onClick={() =>
                                                                setPendingDelete(
                                                                    entry,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="size-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Source</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Size</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rows.map((entry) => (
                                            <TableRow
                                                key={`${entry.source}-${entry.id}`}
                                            >
                                                <TableCell className="max-w-xs truncate font-medium">
                                                    {fileName(entry)}
                                                </TableCell>
                                                <TableCell>
                                                    <SourceBadge
                                                        entry={entry}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    {entry.is_generated
                                                        ? (entry.job_type_label ??
                                                          'Generated')
                                                        : entry.type_label}
                                                </TableCell>
                                                <TableCell>
                                                    {formatSize(entry.size)}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {formatDate(
                                                        entry.created_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        {entry.source ===
                                                            'upload' && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Preview file"
                                                                onClick={() =>
                                                                    setPreviewFile(
                                                                        entry,
                                                                    )
                                                                }
                                                            >
                                                                <Eye className="size-4" />
                                                            </Button>
                                                        )}
                                                        {entry.is_generated &&
                                                            entry.job_id && (
                                                                <Button
                                                                    asChild
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label="View job"
                                                                >
                                                                    <a
                                                                        href={activityIndex.url(
                                                                            {
                                                                                query: {
                                                                                    job_id: entry.job_id,
                                                                                },
                                                                            },
                                                                        )}
                                                                    >
                                                                        <ArrowUpRight className="size-4" />
                                                                    </a>
                                                                </Button>
                                                            )}
                                                        {entry.download_url && (
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Download"
                                                            >
                                                                <a
                                                                    href={
                                                                        entry.download_url
                                                                    }
                                                                >
                                                                    <Download className="size-4" />
                                                                </a>
                                                            </Button>
                                                        )}
                                                        {entry.source ===
                                                            'upload' && (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Delete file"
                                                                onClick={() =>
                                                                    setPendingDelete(
                                                                        entry,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        <DataTablePagination
                            meta={files.meta}
                            onPageChange={(page) => navigate({ page })}
                        />
                    </>
                ) : (
                    <EmptyState
                        icon={FolderOpen}
                        title={
                            source === 'generated'
                                ? 'No generated files yet'
                                : 'No files yet'
                        }
                        description={
                            source === 'generated'
                                ? 'Completed exports and reports will appear here.'
                                : 'Upload a file to see it here.'
                        }
                    />
                )}
            </div>

            <ConfirmDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                    }
                }}
                title="Delete file?"
                description={
                    pendingDelete
                        ? `"${fileName(pendingDelete)}" will be permanently deleted. This cannot be undone.`
                        : undefined
                }
                confirmLabel="Delete"
                destructive
                onConfirm={confirmDelete}
            />

            <FilePreviewDialog
                file={
                    previewFile
                        ? {
                              name: fileName(previewFile),
                              mime_type: previewFile.mime_type,
                              url: previewFile.url,
                              type_label: previewFile.type_label,
                              size: previewFile.size,
                          }
                        : null
                }
                open={previewFile !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewFile(null);
                    }
                }}
            />
        </>
    );
}
