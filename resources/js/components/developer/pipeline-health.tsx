import { RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatRelativeTime } from '@/lib/format';
import type { PipelineHealthStatus } from '@/types';

type Props = {
    status: PipelineHealthStatus;
    lastRefreshedAt: number | null;
    isRefreshing: boolean;
    onRefresh: () => void;
};

const STATUS_LABELS: Record<PipelineHealthStatus, string> = {
    healthy: 'Healthy',
    degraded: 'Degraded',
    unhealthy: 'Unhealthy',
};

function statusVariant(
    status: PipelineHealthStatus,
): 'default' | 'secondary' | 'destructive' {
    switch (status) {
        case 'healthy':
            return 'default';
        case 'degraded':
            return 'secondary';
        default:
            return 'destructive';
    }
}

export function PipelineHealth({
    status,
    lastRefreshedAt,
    isRefreshing,
    onRefresh,
}: Props) {
    const { t } = useTranslation();

    return (
        <div className="flex flex-wrap items-center gap-3">
            <Badge variant={statusVariant(status)}>
                {t(STATUS_LABELS[status])}
            </Badge>
            {lastRefreshedAt !== null && (
                <span className="text-sm text-muted-foreground">
                    {t('Updated :time', {
                        time: formatRelativeTime(
                            new Date(lastRefreshedAt).toISOString(),
                        ),
                    })}
                </span>
            )}
            <Button
                variant="outline"
                size="sm"
                onClick={onRefresh}
                disabled={isRefreshing}
            >
                <RefreshCw
                    className={isRefreshing ? 'size-4 animate-spin' : 'size-4'}
                />
                {t('Refresh')}
            </Button>
        </div>
    );
}
