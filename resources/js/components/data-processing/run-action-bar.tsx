import {
    Ban,
    Clock,
    Copy,
    Download,
    Play,
    RotateCcw,
    Trash2,
    Wifi,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import {
    cancelConfirmation,
    deleteConfirmation,
    runActions,
    type RecoveryAction,
} from '@/components/data-processing/job-recovery';
import { Button } from '@/components/ui/button';
import { cancel, destroy, duplicate, resume, retry } from '@/routes/activity';
import { download } from '@/routes/exports';
import { useJobAction } from '@/hooks/use-job-action';
import { useTranslation } from '@/hooks/use-translation';
import type { DataProcessingJob } from '@/types';

type Props = {
    run: DataProcessingJob;
};

const ICONS: Record<RecoveryAction['key'], LucideIcon> = {
    cancel: Ban,
    retry: RotateCcw,
    resume: Play,
    reconnect: Wifi,
    wait: Clock,
    duplicate: Copy,
    download: Download,
    delete: Trash2,
};

/**
 * PIPE-07 — the status-aware action bar. The primary action is chosen from the
 * run's status and failure reason; the rest collapse into secondary buttons.
 * Destructive/cooperative stops are confirmed with real consequences.
 */
export function RunActionBar({ run }: Props) {
    const { t } = useTranslation();
    const { pending, run: act } = useJobAction();
    const [confirming, setConfirming] = useState<'cancel' | 'delete' | null>(
        null,
    );

    const { primary, secondary } = runActions(run, t);

    if (primary === null && secondary.length === 0) {
        return null;
    }

    const execute = (action: RecoveryAction): void => {
        if (action.key === 'cancel') {
            act('cancel', cancel.url(run.id));
        } else if (action.key === 'retry') {
            act('retry', retry.url(run.id));
        } else if (action.key === 'resume') {
            act('resume', resume.url(run.id));
        } else if (action.key === 'duplicate') {
            act('duplicate', duplicate.url(run.id));
        } else if (action.key === 'delete') {
            act('delete', destroy.url(run.id), { method: 'delete' });
        }
    };

    const onAction = (action: RecoveryAction): void => {
        if (action.confirm) {
            setConfirming(action.key === 'delete' ? 'delete' : 'cancel');

            return;
        }

        execute(action);
    };

    const renderAction = (
        action: RecoveryAction,
        prominent: boolean,
    ): ReactNode => {
        const Icon = ICONS[action.key];
        const busy = pending === action.key;
        const busyElsewhere = pending !== null && !busy;

        if (action.key === 'download') {
            return (
                <Button
                    key={action.key}
                    asChild
                    variant={prominent ? 'default' : 'outline'}
                    size="sm"
                >
                    {/* Streamed file — a native anchor, not Inertia Link. */}
                    <a href={download.url(run.id)}>
                        <Icon className="size-4" />
                        {t('Download')}
                    </a>
                </Button>
            );
        }

        return (
            <Button
                key={action.key}
                type="button"
                variant={
                    action.destructive
                        ? 'ghost'
                        : prominent
                          ? 'default'
                          : 'outline'
                }
                size="sm"
                loading={busy}
                disabled={busyElsewhere || action.disabled}
                title={
                    action.disabled
                        ? (run.failure?.hint ?? undefined)
                        : undefined
                }
                onClick={() => onAction(action)}
            >
                <Icon className="size-4" />
                {action.label}
            </Button>
        );
    };

    return (
        <div
            data-slot="run-action-bar"
            className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-card p-3"
        >
            <div className="flex flex-wrap items-center gap-2">
                {primary && renderAction(primary, true)}
                {secondary.map((action) => renderAction(action, false))}
            </div>

            {run.abilities.duplicate_soon && (
                <p className="w-full text-xs text-muted-foreground">
                    {t('A run with the same settings was started moments ago.')}
                </p>
            )}

            <ConfirmDialog
                open={confirming === 'cancel'}
                onOpenChange={(open) => setConfirming(open ? 'cancel' : null)}
                title={cancelConfirmation(run, t).title}
                description={cancelConfirmation(run, t).description}
                confirmLabel={t('Stop')}
                destructive
                loading={pending === 'cancel'}
                onConfirm={() => {
                    setConfirming(null);
                    execute({ key: 'cancel', label: t('Stop') });
                }}
            />

            <ConfirmDialog
                open={confirming === 'delete'}
                onOpenChange={(open) => setConfirming(open ? 'delete' : null)}
                title={deleteConfirmation(run, t).title}
                description={deleteConfirmation(run, t).description}
                confirmLabel={t('Delete')}
                destructive
                loading={pending === 'delete'}
                onConfirm={() => {
                    setConfirming(null);
                    execute({ key: 'delete', label: t('Delete') });
                }}
            />
        </div>
    );
}
