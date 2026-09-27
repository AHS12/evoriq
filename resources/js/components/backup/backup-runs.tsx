import { router } from '@inertiajs/react';
import { Download, TerminalSquare } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { appLocale } from '@/lib/locale';
import { download } from '@/routes/admin/settings/backup';
import type { BackupRun, BackupRunStatus, Paginated } from '@/types';

type Props = {
    runs: Paginated<BackupRun>;
};

const statusVariants: Record<
    BackupRunStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    running: 'secondary',
    completed: 'default',
    failed: 'destructive',
};

function formatTimestamp(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString(appLocale(), {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
}

export function BackupRuns({ runs }: Props) {
    const { t } = useTranslation();
    const [active, setActive] = useState<BackupRun | null>(null);

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>{t('Run history')}</CardTitle>
                    <CardDescription>
                        {t(
                            'Every backup, cleanup and health check with its outcome. The list refreshes live while a backup runs.',
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {runs.data.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('No runs recorded yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {runs.data.map((backupRun) => (
                                <li
                                    key={backupRun.id}
                                    className="flex items-center gap-4 py-3"
                                >
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-sm font-medium">
                                                {t(backupRun.type_label)}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariants[
                                                        backupRun.status
                                                    ]
                                                }
                                            >
                                                {backupRun.status ===
                                                    'running' && (
                                                    <Spinner className="size-3" />
                                                )}
                                                {t(backupRun.status_label)}
                                            </Badge>
                                            <Badge variant="outline">
                                                {t(backupRun.trigger_label)}
                                            </Badge>
                                            {backupRun.user && (
                                                <span className="text-xs text-muted-foreground">
                                                    {backupRun.user.name}
                                                </span>
                                            )}
                                        </div>
                                        {backupRun.file_name && (
                                            <code className="block truncate text-xs text-muted-foreground">
                                                {backupRun.file_name}
                                            </code>
                                        )}
                                        {backupRun.error_message && (
                                            <p className="truncate text-xs text-destructive">
                                                {backupRun.error_message}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex flex-col items-end gap-1">
                                        <span className="text-xs text-muted-foreground">
                                            {formatTimestamp(
                                                backupRun.created_at,
                                            )}
                                        </span>
                                        {backupRun.file_size !== null && (
                                            <span className="text-xs text-muted-foreground">
                                                {formatSize(
                                                    backupRun.file_size,
                                                )}
                                            </span>
                                        )}
                                        <div className="flex gap-1">
                                            {(backupRun.output ||
                                                backupRun.error_message) && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setActive(backupRun)
                                                    }
                                                >
                                                    <TerminalSquare className="size-4" />
                                                    {t('Output')}
                                                </Button>
                                            )}
                                            {backupRun.downloadable && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    {/* A native anchor, not
                                                    Inertia Link: the response
                                                    is a streamed file, which
                                                    Inertia cannot handle. */}
                                                    <a
                                                        href={download.url({
                                                            backupRun:
                                                                backupRun.id,
                                                        })}
                                                    >
                                                        <Download className="size-4" />
                                                        {t('Download')}
                                                    </a>
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    {runs.meta.last_page > 1 && (
                        <div className="mt-4 flex items-center justify-between text-sm">
                            <span className="text-muted-foreground">
                                {t('Page :page of :pages', {
                                    page: runs.meta.current_page,
                                    pages: runs.meta.last_page,
                                })}
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={runs.links.prev === null}
                                    onClick={() =>
                                        router.get(
                                            runs.links.prev ?? '',
                                            {},
                                            {
                                                preserveScroll: true,
                                                only: ['runs'],
                                            },
                                        )
                                    }
                                >
                                    {t('Previous')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={runs.links.next === null}
                                    onClick={() =>
                                        router.get(
                                            runs.links.next ?? '',
                                            {},
                                            {
                                                preserveScroll: true,
                                                only: ['runs'],
                                            },
                                        )
                                    }
                                >
                                    {t('Next')}
                                </Button>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={active !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActive(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {active ? t(active.type_label) : ''}
                        </DialogTitle>
                        <DialogDescription>
                            {active && formatTimestamp(active.created_at)}
                        </DialogDescription>
                    </DialogHeader>
                    <pre className="max-h-96 overflow-auto rounded-lg border bg-muted/50 p-4 text-xs whitespace-pre-wrap">
                        {active?.error_message ??
                            active?.output ??
                            t('No output.')}
                    </pre>
                </DialogContent>
            </Dialog>
        </>
    );
}
