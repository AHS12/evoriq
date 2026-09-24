import { Head } from '@inertiajs/react';
import { Activity, Download, Upload } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { ExportDialog } from '@/components/data-processing/export-dialog';
import { ImportDialog } from '@/components/data-processing/import-dialog';
import { JobDetailSheet } from '@/components/data-processing/job-detail-sheet';
import { JobFilters } from '@/components/data-processing/job-filters';
import { JobList } from '@/components/data-processing/job-list';
import { JobStatCards } from '@/components/data-processing/job-stat-cards';
import { Button } from '@/components/ui/button';
import { useCan } from '@/hooks/use-can';
import { useDataTableFilters } from '@/hooks/use-data-table-filters';
import { useJobPoll } from '@/hooks/use-job-poll';
import { useJobTransitions } from '@/hooks/use-job-transitions';
import { index } from '@/routes/activity';
import type {
    DataProcessingJob,
    JobFilters as JobFiltersType,
    JobOptions,
    JobStats,
    JobStatus,
    JobType,
    Paginated,
} from '@/types';

type Props = {
    jobs: Paginated<DataProcessingJob>;
    stats: JobStats;
    activeJobs: number;
    filters: JobFiltersType;
    options: JobOptions;
};

export default function DataProcessingIndex({
    jobs,
    stats,
    activeJobs,
    filters,
    options,
}: Props) {
    const can = useCan();

    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState<JobType | 'all'>(filters.type ?? 'all');
    const [status, setStatus] = useState<JobStatus | 'all'>(
        filters.status ?? 'all',
    );
    const [selected, setSelected] = useState<DataProcessingJob | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [exportOpen, setExportOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);

    const { apply } = useDataTableFilters(
        index.url(),
        {
            search: search || undefined,
            type: type === 'all' ? undefined : type,
            status: status === 'all' ? undefined : status,
        },
        { only: ['jobs', 'stats', 'activeJobs'] },
    );

    const { live, pause, resume } = useJobPoll(activeJobs > 0);
    useJobTransitions(jobs.data);

    const canExport = can('user.export') || can('export.create');
    const canImport = can('user.import') || can('import.create');

    const openJob = (job: DataProcessingJob): void => {
        setSelected(job);
        setSheetOpen(true);
    };

    const selectStatus = (next: JobStatus | 'all'): void => {
        const value = next === status ? 'all' : next;
        setStatus(value);
        apply({ status: value === 'all' ? undefined : value, page: 1 }, true);
    };

    return (
        <>
            <Head title="Job activity" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Job activity"
                    description="Background jobs — imports, exports and reports."
                    actions={
                        <>
                            {canImport && (
                                <Button
                                    variant="outline"
                                    onClick={() => setImportOpen(true)}
                                >
                                    <Upload />
                                    Import
                                </Button>
                            )}
                            {canExport && (
                                <Button onClick={() => setExportOpen(true)}>
                                    <Download />
                                    Export
                                </Button>
                            )}
                        </>
                    }
                />

                <JobStatCards
                    stats={stats}
                    selected={status}
                    onSelect={selectStatus}
                />

                <JobFilters
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        apply({ search: value || undefined, page: 1 });
                    }}
                    type={type}
                    onTypeChange={(value) => {
                        setType(value);
                        apply(
                            {
                                type: value === 'all' ? undefined : value,
                                page: 1,
                            },
                            true,
                        );
                    }}
                    status={status}
                    onStatusChange={selectStatus}
                    statuses={options.statuses}
                    types={options.types}
                    live={live}
                    onToggleLive={() => (live ? pause() : resume())}
                />

                {!live && activeJobs > 0 && (
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-dashed px-3 py-2 text-sm text-muted-foreground">
                        <span>Live updates are paused.</span>
                        <Button variant="ghost" size="sm" onClick={resume}>
                            Resume
                        </Button>
                    </div>
                )}

                <div
                    className="space-y-4"
                    aria-live="polite"
                    aria-busy={activeJobs > 0}
                >
                    {jobs.data.length > 0 ? (
                        <>
                            <JobList jobs={jobs.data} onOpen={openJob} />
                            <DataTablePagination
                                meta={jobs.meta}
                                onPageChange={(page) => apply({ page }, true)}
                            />
                        </>
                    ) : (
                        <EmptyState
                            icon={Activity}
                            title="Nothing running"
                            description="Imports and exports you start will show up here with live progress."
                            action={
                                canExport ? (
                                    <Button onClick={() => setExportOpen(true)}>
                                        <Download />
                                        Start an export
                                    </Button>
                                ) : undefined
                            }
                        />
                    )}
                </div>
            </div>

            <JobDetailSheet
                job={selected}
                open={sheetOpen}
                onOpenChange={setSheetOpen}
            />

            <ExportDialog
                open={exportOpen}
                onOpenChange={setExportOpen}
                options={options}
            />

            <ImportDialog
                open={importOpen}
                onOpenChange={setImportOpen}
                options={options}
            />
        </>
    );
}
