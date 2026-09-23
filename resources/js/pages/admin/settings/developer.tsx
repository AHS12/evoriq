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
import type {
    DeveloperTool,
    HealthCheck,
    MaintenanceConfig,
    SystemInfo,
} from '@/types';

type Props = {
    tools: DeveloperTool[];
    health: HealthCheck[];
    system: SystemInfo;
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
    maintenance,
}: Props) {
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
            <Head title="Developer settings" />

            <Tabs defaultValue="overview" className="space-y-6">
                <TabsList>
                    <TabsTrigger value="overview">
                        <LayoutDashboard />
                        Overview
                    </TabsTrigger>
                    <TabsTrigger value="commands">
                        <Wrench />
                        Commands
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
                                <CardTitle>System</CardTitle>
                                <CardDescription>
                                    Runtime and cache status.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow
                                    label="Environment"
                                    value={system.environment}
                                />
                                <InfoRow
                                    label="PHP"
                                    value={system.php_version}
                                />
                                <InfoRow
                                    label="Laravel"
                                    value={system.laravel_version}
                                />
                                <InfoRow
                                    label="Timezone"
                                    value={system.timezone}
                                />
                                <InfoRow
                                    label="Debug mode"
                                    value={system.debug ? 'On' : 'Off'}
                                />
                                <InfoRow
                                    label="Config cached"
                                    value={system.config_cached ? 'Yes' : 'No'}
                                />
                                <InfoRow
                                    label="Routes cached"
                                    value={system.routes_cached ? 'Yes' : 'No'}
                                />
                                <InfoRow
                                    label="Events cached"
                                    value={system.events_cached ? 'Yes' : 'No'}
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
                                <CardTitle>Maintenance disabled</CardTitle>
                                <CardDescription>
                                    In-app maintenance actions are turned off.
                                    Enable the maintenance actions flag to run
                                    commands from the UI.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    )}
                </TabsContent>
            </Tabs>
        </>
    );
}
