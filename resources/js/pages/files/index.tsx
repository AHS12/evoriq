import { Head, router, useForm } from '@inertiajs/react';
import {
    Download,
    Eye,
    FileText,
    FolderOpen,
    LayoutGrid,
    List,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
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
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { destroy, index, store } from '@/routes/files';
import { download } from '@/routes/exports';
import type { PaginationMeta } from '@/types';

type UploadItem = {
    id: number;
    uuid: string;
    type: string;
    type_label: string;
    name: string | null;
    file_name: string | null;
    mime_type: string | null;
    size: number | null;
    url: string | null;
    thumb_url: string | null;
    created_at: string | null;
};

type ReportItem = {
    id: number;
    job_id: string;
    type: string;
    status: string;
    entity_type: string | null;
    format: string | null;
    file_name: string | null;
    file_size: number | null;
    progress_percentage: number;
    downloadable: boolean;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    links: unknown;
    meta: PaginationMeta;
};

type Props = {
    source: 'files' | 'reports';
    files: Paginated<UploadItem> | null;
    reports: Paginated<ReportItem> | null;
    filters: {
        search?: string | null;
        type?: string | null;
    };
    types?: {
        value: string;
        label: string;
    }[];
};

function fileName(file: UploadItem): string {
    return file.name ?? file.file_name ?? 'Untitled file';
}

export default function FilesIndex({
    source,
    files,
    reports,
    filters,
    types = [],
}: Props) {
    const [view, setView] = useState<'grid' | 'list'>('grid');
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? 'all');
    const [pendingDelete, setPendingDelete] = useState<UploadItem | null>(null);
    const [previewFile, setPreviewFile] = useState<UploadItem | null>(null);
    const isFirstRender = useRef(true);

    const upload = useForm<{ file: File | null }>({ file: null });

    const navigate = (params: Record<string, string | number | undefined>) => {
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

    const submitFile = (file: File | null) => {
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

    const confirmDelete = () => {
        if (!pendingDelete) {
            return;
        }

        router.delete(destroy.url(pendingDelete.id), {
            preserveScroll: true,
            onFinish: () => setPendingDelete(null),
        });
    };

    return (
        <>
            <Head title="Files" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Files"
                    description="Browse uploaded files and generated reports."
                />

                <Tabs
                    value={source}
                    onValueChange={(value) =>
                        navigate({ source: value, page: 1 })
                    }
                >
                    <TabsList>
                        <TabsTrigger value="files">Files</TabsTrigger>
                        <TabsTrigger value="reports">Reports</TabsTrigger>
                    </TabsList>
                </Tabs>

                {source === 'files' && (
                    <div className="space-y-6">
                        <Card>
                            <CardContent>
                                <label
                                    htmlFor="file-upload"
                                    onDragOver={(event) =>
                                        event.preventDefault()
                                    }
                                    onDrop={(event) => {
                                        event.preventDefault();
                                        submitFile(
                                            event.dataTransfer.files?.[0] ??
                                                null,
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

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="relative w-full sm:max-w-xs">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
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
                                        <SelectItem value="all">
                                            All types
                                        </SelectItem>
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
                                        setView(
                                            view === 'grid' ? 'list' : 'grid',
                                        )
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

                        {files && files.data.length > 0 ? (
                            <>
                                {view === 'grid' ? (
                                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                        {files.data.map((file) => (
                                            <Card key={file.id}>
                                                <CardContent className="space-y-3">
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setPreviewFile(file)
                                                        }
                                                        className="flex aspect-video w-full items-center justify-center overflow-hidden rounded-md bg-muted"
                                                        aria-label={`Preview ${fileName(file)}`}
                                                    >
                                                        <FileThumbnail
                                                            name={fileName(
                                                                file,
                                                            )}
                                                            mimeType={
                                                                file.mime_type
                                                            }
                                                            thumbUrl={
                                                                file.thumb_url
                                                            }
                                                            url={file.url}
                                                        />
                                                    </button>
                                                    <div className="min-w-0">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                setPreviewFile(
                                                                    file,
                                                                )
                                                            }
                                                            className="block max-w-full truncate text-left text-sm font-medium hover:underline"
                                                        >
                                                            {fileName(file)}
                                                        </button>
                                                        <p className="text-xs text-muted-foreground">
                                                            {formatSize(
                                                                file.size,
                                                            )}{' '}
                                                            · {file.type_label}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <Badge variant="outline">
                                                            {file.type_label}
                                                        </Badge>
                                                        <div className="flex items-center gap-1">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Preview file"
                                                                onClick={() =>
                                                                    setPreviewFile(
                                                                        file,
                                                                    )
                                                                }
                                                            >
                                                                <Eye className="size-4" />
                                                            </Button>
                                                            {file.url && (
                                                                <Button
                                                                    asChild
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label="Download"
                                                                >
                                                                    <a
                                                                        href={
                                                                            file.url
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                    >
                                                                        <Download className="size-4" />
                                                                    </a>
                                                                </Button>
                                                            )}
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Delete file"
                                                                onClick={() =>
                                                                    setPendingDelete(
                                                                        file,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
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
                                                    <TableHead>Type</TableHead>
                                                    <TableHead>Size</TableHead>
                                                    <TableHead className="text-right">
                                                        Actions
                                                    </TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {files.data.map((file) => (
                                                    <TableRow key={file.id}>
                                                        <TableCell className="font-medium">
                                                            {fileName(file)}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline">
                                                                {
                                                                    file.type_label
                                                                }
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            {formatSize(
                                                                file.size,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-right">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Preview file"
                                                                onClick={() =>
                                                                    setPreviewFile(
                                                                        file,
                                                                    )
                                                                }
                                                            >
                                                                <Eye className="size-4" />
                                                            </Button>
                                                            {file.url && (
                                                                <Button
                                                                    asChild
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label="Download"
                                                                >
                                                                    <a
                                                                        href={
                                                                            file.url
                                                                        }
                                                                        target="_blank"
                                                                        rel="noreferrer"
                                                                    >
                                                                        <Download className="size-4" />
                                                                    </a>
                                                                </Button>
                                                            )}
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="Delete file"
                                                                onClick={() =>
                                                                    setPendingDelete(
                                                                        file,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
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
                                title="No files yet"
                                description="Upload a file to see it here."
                            />
                        )}
                    </div>
                )}

                {source === 'reports' && (
                    <div className="space-y-4">
                        {reports && reports.data.length > 0 ? (
                            <>
                                <div className="overflow-hidden rounded-xl border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Report</TableHead>
                                                <TableHead>Format</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead>Size</TableHead>
                                                <TableHead className="text-right">
                                                    Actions
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {reports.data.map((report) => (
                                                <TableRow key={report.id}>
                                                    <TableCell className="font-medium">
                                                        {report.file_name ??
                                                            report.entity_type ??
                                                            report.job_id}
                                                    </TableCell>
                                                    <TableCell>
                                                        {report.format?.toUpperCase() ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge
                                                            variant={
                                                                report.status ===
                                                                'completed'
                                                                    ? 'default'
                                                                    : report.status ===
                                                                        'failed'
                                                                      ? 'destructive'
                                                                      : 'secondary'
                                                            }
                                                        >
                                                            {report.status}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        {formatSize(
                                                            report.file_size,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        {report.downloadable ? (
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <a
                                                                    href={download.url(
                                                                        report.id,
                                                                    )}
                                                                >
                                                                    <Download className="size-4" />
                                                                    Download
                                                                </a>
                                                            </Button>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">
                                                                {
                                                                    report.progress_percentage
                                                                }
                                                                %
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                <DataTablePagination
                                    meta={reports.meta}
                                    onPageChange={(page) => navigate({ page })}
                                />
                            </>
                        ) : (
                            <EmptyState
                                icon={FileText}
                                title="No reports yet"
                                description="Generated exports will appear here."
                            />
                        )}
                    </div>
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
