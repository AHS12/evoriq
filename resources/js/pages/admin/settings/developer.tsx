import { Head, usePoll } from '@inertiajs/react';
import { LayoutDashboard, Wrench } from 'lucide-react';
import { useEffect } from 'react';
import { CommandRuns } from '@/components/developer/command-runs';
import { HealthList } from '@/components/developer/health-list';
import { MaintenanceActions } from '@/components/developer/maintenance-actions';
import { ToolCard } from '@/components/developer/tool-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslation } from '@/hooks/use-translation';
import { appLocale } from '@/lib/locale';
import type {
    BackupDevStatus,
    DeveloperTool,
    HealthCheck,
    MaintenanceConfig,
    SystemInfo,
} from '@/types';

type Props = {
    tools: DeveloperTool[];
    health: HealthCheck[];
    system: SystemInfo;
    backup: BackupDevStatus;
    maintenance: MaintenanceConfig;
};

function InfoRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 py-2.5 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

export default function DeveloperSettings({
    tools,
    health,
    system,
    backup,
    maintenance,
}: Props) {
    const { t } = useTranslation();
    const activeRuns = maintenance.runs.filter((run) => !run.finished).length;
    const hasActiveRun = activeRuns > 0;
    const { start, stop } = usePoll(
        2500,
        { only: ['maintenance'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasActiveRun) {
            start();
        } else {
            stop();
        }
    }, [hasActiveRun, start, stop]);

    return (
        <>
            <Head title={t('Developer settings')} />

            <Tabs defaultValue="overview" className="space-y-6">
                <TabsList>
                    <TabsTrigger value="overview">
                        <LayoutDashboard />
                        {t('Overview')}
                    </TabsTrigger>
                    <TabsTrigger value="commands">
                        <Wrench />
                        {t('Commands')}
                        {activeRuns > 0 && (
                            <Badge variant="secondary" className="ml-1">
                                {activeRuns}
                            </Badge>
                        )}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-3">
                        {tools.map((tool) => (
                            <ToolCard key={tool.key} tool={tool} />
                        ))}
                    </div>

                    <div className="grid items-start gap-4 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('System')}</CardTitle>
                                <CardDescription>
                                    {t('Runtime, cache and backup status.')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow
                                    label={t('Environment')}
                                    value={system.environment}
                                />
                                <InfoRow
                                    label={t('PHP')}
                                    value={system.php_version}
                                />
                                <InfoRow
                                    label={t('Laravel')}
                                    value={system.laravel_version}
                                />
                                <InfoRow
                                    label={t('Timezone')}
                                    value={system.timezone}
                                />
                                <InfoRow
                                    label={t('Debug mode')}
                                    value={system.debug ? t('On') : t('Off')}
                                />
                                <InfoRow
                                    label={t('Config cached')}
                                    value={
                                        system.config_cached
                                            ? t('Yes')
                                            : t('No')
                                    }
                                />
                                <InfoRow
                                    label={t('Routes cached')}
                                    value={
                                        system.routes_cached
                                            ? t('Yes')
                                            : t('No')
                                    }
                                />
                                <InfoRow
                                    label={t('Events cached')}
                                    value={
                                        system.events_cached
                                            ? t('Yes')
                                            : t('No')
                                    }
                                />
                                <InfoRow
                                    label={t('Next backup run')}
                                    value={
                                        backup.next_run_at
                                            ? new Date(
                                                  backup.next_run_at,
                                              ).toLocaleString(appLocale(), {
                                                  dateStyle: 'short',
                                                  timeStyle: 'short',
                                              })
                                            : t('Not scheduled')
                                    }
                                />
                                <InfoRow
                                    label={t('Last backup')}
                                    value={
                                        backup.last_backup_at
                                            ? new Date(
                                                  backup.last_backup_at,
                                              ).toLocaleString(appLocale(), {
                                                  dateStyle: 'short',
                                                  timeStyle: 'short',
                                              })
                                            : t('No backup yet')
                                    }
                                />
                            </CardContent>
                        </Card>

                        <HealthList health={health} />
                    </div>
                </TabsContent>

                <TabsContent value="commands" className="space-y-4">
                    {maintenance.enabled ? (
                        <>
                            <MaintenanceActions
                                actions={maintenance.actions}
                                maintenanceMode={maintenance.mode}
                                runs={maintenance.runs}
                            />
                            <CommandRuns
                                runs={maintenance.runs}
                                actions={maintenance.actions}
                            />
                        </>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('Maintenance disabled')}
                                </CardTitle>
                                <CardDescription>
                                    {t(
                                        'In-app maintenance actions are turned off. Enable the maintenance actions flag to run commands from the UI.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    )}
                </TabsContent>
            </Tabs>
        </>
    );
}
