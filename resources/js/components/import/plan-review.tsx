import { Clock, Database, ListTree, Server } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDuration, formatNumber } from '@/lib/format';
import type { ImportPlan } from '@/types/import';

type Props = {
    plan: ImportPlan;
    isFreePlan: boolean;
    starting: boolean;
    onStart: () => void;
    onBack: () => void;
};

/**
 * PIPE-09 step 3 — the honest review: what will run, roughly how long, and the
 * Free-plan reality baked into the caveats.
 */
export function PlanReview({
    plan,
    isFreePlan,
    starting,
    onStart,
    onBack,
}: Props) {
    const { t } = useTranslation();

    const entities = new Set(
        plan.phases.flatMap((phase) =>
            phase.jobs.map((job) => job.entity_type),
        ),
    );

    const cards = [
        {
            icon: Database,
            label: t('Entities'),
            value: formatNumber(entities.size),
        },
        {
            icon: Server,
            label: t('Estimated API requests'),
            value: formatNumber(plan.estimate.requests),
        },
        {
            icon: Clock,
            label: t('Estimated duration'),
            value: formatDuration(plan.estimate.estimated_seconds),
        },
        {
            icon: ListTree,
            label: t('Jobs'),
            value: formatNumber(plan.estimate.jobs),
        },
    ];

    return (
        <div className="space-y-6">
            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {cards.map((card) => (
                    <div key={card.label} className="rounded-xl border p-4">
                        <card.icon
                            aria-hidden
                            className="size-4 text-muted-foreground"
                        />
                        <dt className="mt-2 text-xs text-muted-foreground">
                            {card.label}
                        </dt>
                        <dd className="text-lg font-semibold tabular-nums">
                            {card.value}
                        </dd>
                    </div>
                ))}
            </dl>

            <section className="space-y-3">
                <h3 className="text-sm font-medium">
                    {t('How the import runs')}
                </h3>
                <ol className="space-y-2">
                    {plan.phases.map((phase, index) => (
                        <li
                            key={phase.phase}
                            className="flex items-start gap-3 rounded-lg border p-3"
                        >
                            <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium">
                                {index + 1}
                            </span>
                            <span className="min-w-0">
                                <span className="block text-sm font-medium">
                                    {t(phase.phase_label)}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {t(
                                        ':jobs jobs · about :requests API requests',
                                        {
                                            jobs: formatNumber(phase.job_count),
                                            requests: formatNumber(
                                                phase.estimated_requests,
                                            ),
                                        },
                                    )}
                                </span>
                            </span>
                        </li>
                    ))}
                </ol>
            </section>

            {isFreePlan && (
                <p className="rounded-lg border border-warning/40 bg-warning/5 p-3 text-xs text-muted-foreground">
                    {t(
                        'Your plan is rate-limited. A multi-year import may take hours; it runs in the background and resumes automatically if interrupted.',
                    )}
                </p>
            )}

            <p className="text-xs text-muted-foreground">
                {t(
                    'Estimates are approximate; Clockify rate limits apply. Time entries are fetched per user, so adding users increases the work.',
                )}
            </p>

            <div className="flex items-center justify-between gap-2">
                <Button type="button" variant="outline" onClick={onBack}>
                    {t('Back')}
                </Button>
                <Button type="button" onClick={onStart} loading={starting}>
                    {t('Start import')}
                </Button>
            </div>
        </div>
    );
}
