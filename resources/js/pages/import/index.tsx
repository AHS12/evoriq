import { Head, Link, router } from '@inertiajs/react';
import { Plug, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { ConnectDialog } from '@/components/connection/connect-dialog';
import { InlineAlert } from '@/components/feedback/inline-alert';
import { InspectSummary } from '@/components/import/inspect-summary';
import { PlanReview } from '@/components/import/plan-review';
import { RangeStep } from '@/components/import/range-step';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { buildDateRange, toISODate, type DateRange } from '@/lib/date-range';
import { JsonRequestError, postJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import {
    index as importIndex,
    inspect as inspectRoute,
    show as importShow,
    store as storeRoute,
} from '@/routes/import';
import type { DataProcessingJob } from '@/types';
import type {
    ImportPlan,
    ImportWizardProps,
    ImportWorkspace,
} from '@/types/import';

type Step = 'connect' | 'range' | 'review';

const STEPS: { key: Step; label: string }[] = [
    { key: 'connect', label: 'Connect' },
    { key: 'range', label: 'Range' },
    { key: 'review', label: 'Review' },
];

export default function ImportWizard({
    connection,
    workspace,
    activeImport,
    maxHistoryYears,
    regions,
}: ImportWizardProps) {
    const { t } = useTranslation();

    const [connectOpen, setConnectOpen] = useState(false);
    const [step, setStep] = useState<Step>(
        connection === null ? 'connect' : 'range',
    );
    const [range, setRange] = useState<DateRange>(() =>
        buildDateRange('last-year', {
            max: new Date(),
            min: shiftYears(new Date(), -maxHistoryYears),
        }),
    );
    const [plan, setPlan] = useState<ImportPlan | null>(null);
    const [inspecting, setInspecting] = useState(false);
    const [inspectError, setInspectError] = useState<string | null>(null);
    const [starting, setStarting] = useState(false);

    const rangeStart = toISODate(range.start);
    const rangeEnd = toISODate(range.end);

    const runInspect = async (): Promise<ImportPlan | null> => {
        if (!rangeStart || !rangeEnd) {
            setPlan(null);

            return null;
        }

        setInspecting(true);
        setInspectError(null);

        try {
            const result = await postJson<ImportPlan>(inspectRoute.url(), {
                range_start: rangeStart,
                range_end: rangeEnd,
            });
            setPlan(result);

            return result;
        } catch (error) {
            setPlan(null);
            const body =
                error instanceof JsonRequestError
                    ? (error.body as { message?: string } | null)
                    : null;
            setInspectError(
                body?.message
                    ? t(body.message)
                    : t(
                          'The workspace could not be inspected. You can still start the import without an estimate.',
                      ),
            );

            return null;
        } finally {
            setInspecting(false);
        }
    };

    // Debounced inspect whenever the range settles (skipped while an import is
    // already running — inspecting would compete for the same API budget).
    useEffect(() => {
        if (
            connection === null ||
            activeImport !== null ||
            !rangeStart ||
            !rangeEnd
        ) {
            return;
        }

        const timer = setTimeout(() => void runInspect(), 400);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [rangeStart, rangeEnd, connection, activeImport]);

    const goToReview = async (): Promise<void> => {
        if (plan === null) {
            const result = await runInspect();

            if (result === null) {
                return;
            }
        }

        setStep('review');
    };

    const startImport = (): void => {
        if (!rangeStart || !rangeEnd) {
            return;
        }

        setStarting(true);

        router.post(
            storeRoute.url(),
            { range_start: rangeStart, range_end: rangeEnd },
            { onFinish: () => setStarting(false) },
        );
    };

    const activeIndex = STEPS.findIndex((candidate) => candidate.key === step);

    return (
        <>
            <Head title={t('Historical import')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={t('Historical import')}
                    description={t(
                        'Import your Clockify history so Evoriq can analyze it.',
                    )}
                />

                {activeImport && <ActiveImportBanner run={activeImport} />}

                <nav aria-label={t('Import steps')}>
                    <ol className="flex flex-wrap items-center gap-2 text-sm">
                        {STEPS.map((item, index) => {
                            const active = step === item.key;
                            const done = index < activeIndex;

                            return (
                                <li
                                    key={item.key}
                                    aria-current={active ? 'step' : undefined}
                                    className="flex items-center gap-2"
                                >
                                    <span
                                        className={cn(
                                            'flex size-6 items-center justify-center rounded-full text-xs font-medium',
                                            active
                                                ? 'bg-primary text-primary-foreground'
                                                : done
                                                  ? 'bg-success/20 text-success'
                                                  : 'bg-muted text-muted-foreground',
                                        )}
                                    >
                                        {index + 1}
                                    </span>
                                    <span
                                        className={cn(
                                            active
                                                ? 'font-medium'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {t(item.label)}
                                    </span>
                                    {index < STEPS.length - 1 && (
                                        <span
                                            aria-hidden
                                            className="text-muted-foreground"
                                        >
                                            /
                                        </span>
                                    )}
                                </li>
                            );
                        })}
                    </ol>
                </nav>

                {step === 'connect' && (
                    <section className="space-y-4">
                        <h2 className="text-lg font-semibold">
                            {t('Connect Clockify')}
                        </h2>
                        <EmptyState
                            icon={Plug}
                            title={t('No connection yet')}
                            description={t(
                                'Connect Clockify to choose what history to import.',
                            )}
                            action={
                                <Button onClick={() => setConnectOpen(true)}>
                                    <Plug className="size-4" />
                                    {t('Connect Clockify')}
                                </Button>
                            }
                        />
                        <ConnectDialog
                            open={connectOpen}
                            onOpenChange={setConnectOpen}
                            regions={regions}
                        />
                    </section>
                )}

                {step !== 'connect' && connection !== null && (
                    <section className="space-y-6">
                        {workspace && <WorkspaceCard workspace={workspace} />}

                        {step === 'range' && (
                            <>
                                <RangeStep
                                    value={range}
                                    onChange={(next) => {
                                        setPlan(null);
                                        setRange(next);
                                    }}
                                    maxYears={maxHistoryYears}
                                    disabled={inspecting}
                                />

                                <InspectSummary
                                    plan={plan}
                                    loading={inspecting}
                                    error={inspectError}
                                    onRetry={() => void runInspect()}
                                />

                                <div className="flex justify-end">
                                    <Button
                                        type="button"
                                        loading={inspecting}
                                        onClick={() => void goToReview()}
                                    >
                                        {t('Review import')}
                                    </Button>
                                </div>
                            </>
                        )}

                        {step === 'review' && plan !== null && (
                            <PlanReview
                                plan={plan}
                                isFreePlan={connection.plan.is_free}
                                starting={starting}
                                onStart={startImport}
                                onBack={() => setStep('range')}
                            />
                        )}
                    </section>
                )}
            </div>
        </>
    );
}

ImportWizard.layout = {
    breadcrumbs: [{ title: 'Historical import', href: importIndex() }],
};

function shiftYears(date: Date, years: number): Date {
    const next = new Date(date);
    next.setFullYear(next.getFullYear() + years);

    return next;
}

function WorkspaceCard({ workspace }: { workspace: ImportWorkspace }) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border bg-card p-3 text-sm">
            <span className="font-medium">{workspace.name}</span>
            {workspace.currency && (
                <span className="text-muted-foreground">
                    {workspace.currency}
                </span>
            )}
            {workspace.time_zone && (
                <span className="text-muted-foreground">
                    {workspace.time_zone}
                </span>
            )}
            <span className="text-xs text-muted-foreground">
                {t('Connected workspace')}
            </span>
        </div>
    );
}

function ActiveImportBanner({ run }: { run: DataProcessingJob }) {
    const { t } = useTranslation();

    return (
        <InlineAlert
            tone="info"
            title={t('An import is already running')}
            action={
                <Button asChild size="sm" variant="outline">
                    <Link href={importShow.url(run.id)}>
                        <RefreshCw className="size-3.5" />
                        {t('Open import')}
                    </Link>
                </Button>
            }
        >
            {t(
                'Finish or cancel the current import before starting another. You can watch its progress live.',
            )}
        </InlineAlert>
    );
}
