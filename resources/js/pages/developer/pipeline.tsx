import { Head, Link } from '@inertiajs/react';
import { CorrelationSearch } from '@/components/developer/correlation-search';
import { HealthList } from '@/components/developer/health-list';
import { PipelineHealth } from '@/components/developer/pipeline-health';
import { PipelineMetricCards } from '@/components/developer/pipeline-metric-cards';
import { QueueDepth } from '@/components/developer/queue-depth';
import { RecentFailures } from '@/components/developer/recent-failures';
import { ToolCard } from '@/components/developer/tool-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';
import { useLivePoll } from '@/hooks/use-live-poll';
import { formatDuration, formatNumber, formatRelativeTime } from '@/lib/format';
import { pipeline } from '@/routes/admin/settings/developer';
import type {
    DeveloperTool,
    HealthCheck,
    PipelineApiBudget,
    PipelineHealthStatus,
    PipelineMetrics,
    PipelineRecentFailure,
    PipelineRecentRun,
    QueueDepth as QueueDepthData,
} from '@/types';

type Props = {
    metrics: PipelineMetrics;
    queue: QueueDepthData;
    health: HealthCheck[];
    status: PipelineHealthStatus;
    recentRuns: PipelineRecentRun[];
    recentFailures: PipelineRecentFailure[];
    apiBudget: PipelineApiBudget | null;
    tools: DeveloperTool[];
};

function runStatusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'processing':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
}

export default function PipelineHealthPage({
    metrics,
    queue,
    health,
    status,
    recentRuns,
    recentFailures,
    apiBudget,
    tools,
}: Props) {
    const { t } = useTranslation();

    const { refresh, isRefreshing, lastRefreshedAt } = useLivePoll({
        url: pipeline.url(),
        only: ['metrics', 'queue', 'health', 'status'],
        enabled: true,
        interval: 20000,
        idleInterval: 60000,
    });

    return (
        <>
            <Head title={t('Pipeline health')} />

            <div className="space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('Pipeline health')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Success rate, throughput, queue depth and recent failures.',
                            )}
                        </p>
                    </div>
                    <PipelineHealth
                        status={status}
                        lastRefreshedAt={lastRefreshedAt}
                        isRefreshing={isRefreshing}
                        onRefresh={refresh}
                    />
                </div>

                <PipelineMetricCards metrics={metrics} />

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-6">
                        <QueueDepth queue={queue} />
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('API budget')}</CardTitle>
                                <CardDescription>
                                    {apiBudget !== null
                                        ? t('Current Clockify API budget.')
                                        : t(
                                              'Not configured — connect a workspace to track the API budget.',
                                          )}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    </div>
                    <RecentFailures failures={recentFailures} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Recent runs')}</CardTitle>
                        <CardDescription>
                            {t('The newest runs across the pipeline.')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('Type')}</TableHead>
                                    <TableHead>{t('Status')}</TableHead>
                                    <TableHead>{t('Entity')}</TableHead>
                                    <TableHead className="text-right">
                                        {t('Records')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Duration')}
                                    </TableHead>
                                    <TableHead className="text-right">
                                        {t('Started')}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentRuns.length === 0 ? (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-center text-muted-foreground"
                                        >
                                            {t('No runs yet.')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    recentRuns.map((run) => (
                                        <TableRow key={run.id}>
                                            <TableCell>
                                                <Link
                                                    href={run.action_url}
                                                    className="font-medium hover:underline"
                                                >
                                                    {run.type_label}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={runStatusVariant(
                                                        run.status,
                                                    )}
                                                >
                                                    {run.status_label}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {run.entity_label ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatNumber(run.records)}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {run.duration_ms === null
                                                    ? '—'
                                                    : formatDuration(
                                                          run.duration_ms /
                                                              1000,
                                                      )}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground">
                                                {run.started_at
                                                    ? formatRelativeTime(
                                                          run.started_at,
                                                      )
                                                    : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Find a run')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Search by correlation or job id, or open the audit log.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <CorrelationSearch runs={recentRuns} />
                            </CardContent>
                        </Card>
                        <HealthList health={health} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        {tools.map((tool) => (
                            <ToolCard key={tool.key} tool={tool} />
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}
