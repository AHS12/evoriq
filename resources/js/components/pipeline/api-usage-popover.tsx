import { Link } from '@inertiajs/react';
import { Gauge, TriangleAlert } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useTranslation } from '@/hooks/use-translation';
import { formatDuration, formatNumber, formatRelativeTime } from '@/lib/format';
import { index as activityIndex } from '@/routes/activity';
import { cn } from '@/lib/utils';
import type { ApiUsage } from '@/types';

type Props = {
    usage: ApiUsage;
    onNavigate: () => void;
};

/** A 1s-ticking countdown derived from the window's reset instant. */
function useRemainingSeconds(resetsAt: string, fallback: number): number {
    const compute = (): number =>
        Math.max(
            0,
            Math.round((new Date(resetsAt).getTime() - Date.now()) / 1000),
        );

    const [seconds, setSeconds] = useState(() => compute());

    useEffect(() => {
        setSeconds(compute());

        const timer = setInterval(() => setSeconds(compute()), 1000);

        return () => clearInterval(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [resetsAt]);

    return Number.isFinite(seconds) ? seconds : fallback;
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
        </div>
    );
}

/**
 * SYNC-17 — the API budget popover: used/remaining/limit, honest Free (hourly)
 * vs paid (per-second) wording, a live reset countdown and the last request.
 */
export function ApiUsagePopover({ usage, onNavigate }: Props) {
    const { t } = useTranslation();
    const remaining = useRemainingSeconds(usage.resets_at, usage.resets_in);
    const free = usage.plan === 'free';

    return (
        <div data-slot="api-usage-popover">
            <div className="flex items-center justify-between gap-2 px-4 py-3">
                <div className="flex items-center gap-2">
                    <Gauge className="size-4 text-muted-foreground" />
                    <p className="text-sm font-medium">
                        {t('Clockify API budget')}
                    </p>
                </div>
                <Badge variant="outline">{free ? t('Free') : t('Paid')}</Badge>
            </div>

            <Separator />

            {free ? (
                <dl className="space-y-2 px-4 py-3 text-sm">
                    <Row label={t('Used')} value={formatNumber(usage.used)} />
                    <Row
                        label={t('Remaining')}
                        value={formatNumber(usage.remaining)}
                    />
                    <Row
                        label={t('Hourly limit')}
                        value={formatNumber(usage.limit)}
                    />
                </dl>
            ) : (
                <div className="space-y-2 px-4 py-3 text-sm">
                    <p className="text-muted-foreground">
                        {t(
                            'Paid plans allow up to :limit requests per second; usage resets continuously.',
                            { limit: formatNumber(usage.limit) },
                        )}
                    </p>
                </div>
            )}

            {free && (
                <>
                    <Separator />
                    <div
                        className={cn(
                            'flex items-center justify-between gap-2 px-4 py-2.5 text-sm',
                            usage.exhausted
                                ? 'text-destructive'
                                : 'text-muted-foreground',
                        )}
                    >
                        <span className="flex items-center gap-2">
                            {usage.exhausted && (
                                <TriangleAlert className="size-4 shrink-0" />
                            )}
                            {t('Resets in :time', {
                                time: formatDuration(remaining),
                            })}
                        </span>
                    </div>
                </>
            )}

            {usage.exhausted && free && (
                <p className="px-4 pb-3 text-xs text-muted-foreground">
                    {t(
                        'The hourly limit is reached. Syncs pause and resume automatically when the window resets.',
                    )}
                </p>
            )}

            {usage.last_request_at && (
                <>
                    <Separator />
                    <p className="px-4 py-2.5 text-xs text-muted-foreground">
                        {t('Last request :time', {
                            time: formatRelativeTime(usage.last_request_at),
                        })}
                    </p>
                </>
            )}

            <Separator />
            <div className="p-2">
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="w-full justify-center"
                >
                    <Link href={activityIndex()} onClick={onNavigate}>
                        {t('View sync activity')}
                    </Link>
                </Button>
            </div>
        </div>
    );
}
