import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatRelativeTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as connectionsIndex } from '@/routes/connections';
import type { ConnectionStatusSummary } from '@/types';

type Props = {
    status: ConnectionStatusSummary;
};

/**
 * CONN-08 — the connection health summary: status dot + plan badge + last
 * verified + workspace, linking to connection management. Never shows key
 * material.
 */
export function ConnectionStatusCard({ status }: Props) {
    const { t } = useTranslation();
    const active = status.status === 'active';

    return (
        <div
            data-slot="connection-status-card"
            className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-4"
        >
            <div className="flex min-w-0 items-center gap-3">
                <span
                    aria-hidden
                    className={cn(
                        'size-2.5 shrink-0 rounded-full',
                        active ? 'bg-success' : 'bg-muted-foreground',
                    )}
                />
                <div className="min-w-0">
                    <p className="truncate font-medium">{status.name}</p>
                    <p className="truncate text-xs text-muted-foreground">
                        {t('Workspace')}: {status.workspace.id ?? t('None')} ·{' '}
                        {status.last_verified_at
                            ? t('Last verified :time', {
                                  time: formatRelativeTime(
                                      status.last_verified_at,
                                  ),
                              })
                            : t('Never verified')}
                    </p>
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Badge variant="outline">
                    {status.plan.is_free ? t('Free') : t('Paid')}
                </Badge>
                <Badge variant={active ? 'default' : 'secondary'}>
                    {t(status.status_label)}
                </Badge>
                <Button asChild size="sm" variant="ghost">
                    <Link href={connectionsIndex()}>{t('Manage')}</Link>
                </Button>
            </div>
        </div>
    );
}
