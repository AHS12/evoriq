import { router } from '@inertiajs/react';
import { Wrench } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import {
    maintenance,
    maintenanceMode as maintenanceModeRoute,
} from '@/routes/admin/settings/developer';
import type { CommandRun, MaintenanceAction } from '@/types';

type Props = {
    actions: MaintenanceAction[];
    maintenanceMode: boolean;
    runs: CommandRun[];
};

export function MaintenanceActions({ actions, maintenanceMode, runs }: Props) {
    const [pending, setPending] = useState<MaintenanceAction | null>(null);
    const [processing, setProcessing] = useState(false);
    const [toggling, setToggling] = useState(false);

    const busy = runs.some((run) => !run.finished);

    const run = () => {
        if (!pending) {
            return;
        }

        setProcessing(true);

        router.post(
            maintenance.url({ action: pending.key }),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setPending(null);
                },
            },
        );
    };

    const toggleMaintenanceMode = (enabled: boolean) => {
        setToggling(true);

        router.post(
            maintenanceModeRoute.url(),
            { enabled },
            {
                preserveScroll: true,
                onFinish: () => setToggling(false),
            },
        );
    };

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Maintenance</CardTitle>
                    <CardDescription>
                        Run maintenance commands. Restricted to super admins.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                        <div>
                            <p className="font-medium">Maintenance mode</p>
                            <p className="text-sm text-muted-foreground">
                                Take the application offline for visitors. The
                                developer tools stay reachable so you can turn
                                it back on.
                            </p>
                        </div>
                        <Switch
                            checked={maintenanceMode}
                            onCheckedChange={toggleMaintenanceMode}
                            disabled={toggling}
                            aria-label="Toggle maintenance mode"
                        />
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {actions.map((action) => (
                            <Button
                                key={action.key}
                                variant="outline"
                                size="sm"
                                disabled={busy}
                                onClick={() => setPending(action)}
                            >
                                <Wrench className="size-4" />
                                {action.label}
                            </Button>
                        ))}
                    </div>

                    {busy && (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner className="size-4" />
                            A command is running. New runs are queued after it
                            finishes.
                        </p>
                    )}
                </CardContent>
            </Card>

            <ConfirmDialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
                title={pending ? `Run "${pending.label}"?` : 'Run action?'}
                description={pending?.description}
                confirmLabel="Run"
                loading={processing}
                onConfirm={run}
            />
        </>
    );
}
