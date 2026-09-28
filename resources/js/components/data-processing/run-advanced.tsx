import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { CopyButton } from '@/components/app/copy-button';
import { RunJsonViewer } from '@/components/data-processing/run-json-viewer';
import { formatDuration, formatDateTime } from '@/lib/format';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useRunEventsTable } from '@/hooks/use-run-events-table';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

/**
 * PIPE-06 — the gated Advanced tab: correlation id, the paginated raw event
 * table, the raw job payload and deep links when permitted. Renders nothing
 * when the backend withheld `advanced` (permission-gated).
 */
export function RunAdvanced({ run }: Props) {
    const { t } = useTranslation();
    const can = useCan();
    const advanced = run.advanced;

    const [expanded, setExpanded] = useState<number | null>(null);
    const { events, meta, page, setPage, loading } = useRunEventsTable(
        run.id,
        advanced !== null,
    );

    if (!advanced) {
        return null;
    }

    return (
        <div className="space-y-6">
            <section className="space-y-2">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Correlation ID')}
                </h3>
                {advanced.correlation_id ? (
                    <CopyButton
                        value={advanced.correlation_id}
                        label={advanced.correlation_id}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {t('No correlation id was recorded.')}
                    </p>
                )}
            </section>

            <section className="space-y-2">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Event table')}
                </h3>

                <div className="overflow-hidden rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-40">
                                    {t('Time')}
                                </TableHead>
                                <TableHead className="w-36">
                                    {t('Type')}
                                </TableHead>
                                <TableHead className="w-20">
                                    {t('Level')}
                                </TableHead>
                                <TableHead>{t('Message')}</TableHead>
                                <TableHead className="w-20 text-right">
                                    {t('Duration')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {loading && events.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-sm text-muted-foreground"
                                    >
                                        {t('Loading events…')}
                                    </TableCell>
                                </TableRow>
                            ) : events.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-sm text-muted-foreground"
                                    >
                                        {t('No events recorded.')}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                events.map((event) => {
                                    const open = expanded === event.id;

                                    return (
                                        <TableRow
                                            key={event.id}
                                            className="cursor-pointer align-top"
                                            onClick={() =>
                                                setExpanded(
                                                    open ? null : event.id,
                                                )
                                            }
                                        >
                                            <TableCell className="text-xs whitespace-nowrap text-muted-foreground">
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1"
                                                    aria-expanded={open}
                                                >
                                                    <ChevronDown
                                                        aria-hidden
                                                        className={cn(
                                                            'size-3.5 transition-transform',
                                                            open &&
                                                                'rotate-180',
                                                        )}
                                                    />
                                                    {formatDateTime(
                                                        event.occurred_at,
                                                        { seconds: true },
                                                    )}
                                                </button>
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {t(event.type_label)}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {t(event.level)}
                                            </TableCell>
                                            <TableCell className="text-xs">
                                                {event.message ??
                                                    (event.stage
                                                        ? `${t('Stage')}: ${event.stage}`
                                                        : '—')}
                                                {open && (
                                                    <RunJsonViewer
                                                        value={event.context}
                                                        truncated={
                                                            event.context_truncated
                                                        }
                                                        className="mt-2"
                                                    />
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right text-xs text-muted-foreground tabular-nums">
                                                {event.duration_ms != null
                                                    ? formatDuration(
                                                          event.duration_ms /
                                                              1000,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {meta && meta.last_page > 1 && (
                    <div className="flex items-center justify-between text-xs text-muted-foreground">
                        <span>
                            {t(':current / :total', {
                                current: meta.current_page,
                                total: meta.last_page,
                            })}
                        </span>
                        <div className="flex gap-1">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={page <= 1}
                                onClick={() => setPage(page - 1)}
                            >
                                <ChevronLeft className="size-4" />
                                {t('Previous')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={page >= meta.last_page}
                                onClick={() => setPage(page + 1)}
                            >
                                {t('Next')}
                                <ChevronRight className="size-4" />
                            </Button>
                        </div>
                    </div>
                )}
            </section>

            <section className="space-y-2">
                <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    {t('Raw job data')}
                </h3>
                <RunJsonViewer value={advanced.raw} />
            </section>

            <section className="flex flex-wrap gap-2">
                {can('audit.view') && (
                    <Button asChild variant="outline" size="sm">
                        <a href="/audit-logs">{t('Audit log')}</a>
                    </Button>
                )}
                {can('developer') && (
                    <>
                        <Button asChild variant="outline" size="sm">
                            <a href="/telescope">Telescope</a>
                        </Button>
                        <Button asChild variant="outline" size="sm">
                            <a href="/pulse">Pulse</a>
                        </Button>
                    </>
                )}
            </section>
        </div>
    );
}
