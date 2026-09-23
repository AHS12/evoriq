import { TerminalSquare } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { CommandRun, CommandRunStatus, MaintenanceAction } from '@/types';

type Props = {
    runs: CommandRun[];
    actions: MaintenanceAction[];
};

const statusVariants: Record<
    CommandRunStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'secondary',
    running: 'secondary',
    completed: 'default',
    failed: 'destructive',
};

function formatTimestamp(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString(undefined, {
        dateStyle: 'short',
        timeStyle: 'short',
    });
}

export function CommandRuns({ runs, actions }: Props) {
    const [active, setActive] = useState<CommandRun | null>(null);

    const labels = new Map(actions.map((action) => [action.key, action.label]));

    return (
        <>
            <Card>
                <CardHeader>
                    <CardTitle>Recent runs</CardTitle>
                    <CardDescription>
                        Commands run in the background. Progress updates live.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    {runs.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No commands have been run yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {runs.map((run) => (
                                <li
                                    key={run.id}
                                    className="flex items-center gap-4 py-3"
                                >
                                    <div className="min-w-0 flex-1 space-y-1.5">
                                        <div className="flex items-center gap-2">
                                            <span className="truncate text-sm font-medium">
                                                {labels.get(run.action) ??
                                                    run.action}
                                            </span>
                                            <Badge
                                                variant={
                                                    statusVariants[run.status]
                                                }
                                            >
                                                {run.status === 'running' && (
                                                    <Spinner className="size-3" />
                                                )}
                                                {run.status_label}
                                            </Badge>
                                        </div>
                                        <code className="block truncate text-xs text-muted-foreground">
                                            {run.command}
                                        </code>
                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-all duration-500',
                                                    run.status === 'failed'
                                                        ? 'bg-destructive'
                                                        : run.status ===
                                                            'completed'
                                                          ? 'bg-primary'
                                                          : 'animate-pulse bg-primary/60',
                                                )}
                                                style={{
                                                    width: `${run.progress}%`,
                                                }}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex flex-col items-end gap-1">
                                        <span className="text-xs text-muted-foreground">
                                            {formatTimestamp(run.created_at)}
                                        </span>
                                        {(run.output || run.error_message) && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setActive(run)}
                                            >
                                                <TerminalSquare className="size-4" />
                                                Output
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={active !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActive(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {active
                                ? (labels.get(active.action) ?? active.action)
                                : ''}
                        </DialogTitle>
                        <DialogDescription>
                            <code>{active?.command}</code>
                        </DialogDescription>
                    </DialogHeader>
                    <pre className="max-h-96 overflow-auto rounded-lg border bg-muted/50 p-4 text-xs whitespace-pre-wrap">
                        {active?.error_message ??
                            active?.output ??
                            'No output.'}
                    </pre>
                </DialogContent>
            </Dialog>
        </>
    );
}
