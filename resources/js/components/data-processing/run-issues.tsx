import { ChevronDown, Download } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { relativeTime } from '@/components/data-processing/job-utils';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { download } from '@/routes/exports';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

/**
 * PIPE-06 — grouped issues. Identical errors are collapsed by `type + message`
 * with a count, expandable to a few sample rows; the failed-rows report (when
 * present) is downloadable.
 */
export function RunIssues({ run }: Props) {
    const { t } = useTranslation();
    const [expanded, setExpanded] = useState<string | null>(null);

    if (run.issues.length === 0) {
        return (
            <EmptyState
                variant="compact"
                title={t('No issues — every row imported cleanly.')}
            />
        );
    }

    const report = run.artifacts.find(
        (artifact) => artifact.downloadable && artifact.download_url,
    );

    return (
        <div className="space-y-3">
            {report && (
                <div className="flex justify-end">
                    <Button asChild variant="outline" size="sm">
                        <a href={report.download_url ?? download.url(run.id)}>
                            <Download className="size-4" />
                            {t('Download failed rows')}
                        </a>
                    </Button>
                </div>
            )}

            <ul className="divide-y overflow-hidden rounded-lg border">
                {run.issues.map((group) => {
                    const key = `${group.type}:${group.message}`;
                    const open = expanded === key;

                    return (
                        <li key={key}>
                            <button
                                type="button"
                                aria-expanded={open}
                                onClick={() => setExpanded(open ? null : key)}
                                className="flex w-full items-center gap-3 px-3 py-2 text-left transition-smooth-fast outline-none hover:bg-muted/40 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <Badge variant="outline" className="shrink-0">
                                    {group.type}
                                </Badge>
                                <span className="min-w-0 flex-1 truncate text-sm">
                                    {group.message}
                                </span>
                                <Badge variant="secondary" className="shrink-0">
                                    {group.count}
                                </Badge>
                                {group.last_seen && (
                                    <span className="hidden shrink-0 text-xs text-muted-foreground sm:inline">
                                        {relativeTime(group.last_seen)}
                                    </span>
                                )}
                                <ChevronDown
                                    aria-hidden
                                    className={cn(
                                        'size-4 shrink-0 text-muted-foreground transition-transform duration-200 ease-standard',
                                        open && 'rotate-180',
                                    )}
                                />
                            </button>

                            {open && (
                                <div className="space-y-2 border-t bg-muted/20 px-3 py-2">
                                    {group.first_seen && group.last_seen && (
                                        <p className="text-xs text-muted-foreground">
                                            {t('First seen')} ·{' '}
                                            {relativeTime(group.first_seen)} ·{' '}
                                            {t('Last seen')} ·{' '}
                                            {relativeTime(group.last_seen)}
                                        </p>
                                    )}

                                    <ul className="space-y-1">
                                        {group.sample_rows.map(
                                            (sample, index) => (
                                                <li
                                                    key={`${sample.row}-${index}`}
                                                    className="flex gap-2 text-xs"
                                                >
                                                    <span className="w-16 shrink-0 text-right text-muted-foreground tabular-nums">
                                                        {sample.row > 0
                                                            ? t('Row :row', {
                                                                  row: sample.row,
                                                              })
                                                            : '—'}
                                                    </span>
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {sample.message}
                                                    </span>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
