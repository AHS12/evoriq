import { isActiveStatus } from '@/components/data-processing/job-utils';
import type { Translate } from '@/components/data-processing/job-utils';
import type { DataProcessingJob } from '@/types';

/**
 * PIPE-07 — the recovery action model. Pure functions so the status-aware
 * primary/secondary actions and their confirmations are unit-testable and
 * shared between the run detail page and the list rows.
 */

export type RecoveryActionKey =
    | 'cancel'
    | 'retry'
    | 'resume'
    | 'reconnect'
    | 'wait'
    | 'duplicate'
    | 'download'
    | 'delete';

export type RecoveryAction = {
    key: RecoveryActionKey;
    label: string;
    /** Requires a confirmation dialog before the request is sent. */
    confirm?: boolean;
    destructive?: boolean;
    disabled?: boolean;
};

export type RecoveryActions = {
    primary: RecoveryAction | null;
    secondary: RecoveryAction[];
};

/** Whether an automatic retry is scheduled and still in the future. */
export function isRetryPending(run: DataProcessingJob): boolean {
    if (run.attempts.next_retry_at === null) {
        return false;
    }

    return new Date(run.attempts.next_retry_at).getTime() > Date.now();
}

/** The attempt chip label, including the max when the queue exposes one. */
export function attemptLabel(run: DataProcessingJob, t: Translate): string {
    const { current, max } = run.attempts;

    if (max > 1) {
        return t('Attempt :current/:max', { current, max });
    }

    return run.attempts.label;
}

/** The failure-driven primary recovery action for a failed run. */
function failedPrimary(run: DataProcessingJob, t: Translate): RecoveryAction {
    switch (run.failure?.action) {
        case 'reconnect':
            // CONN-* owns the actual reconnect flow; the label is surfaced here
            // so the failure reason drives the visible call to action.
            return {
                key: 'reconnect',
                label: t('Reconnect Clockify'),
                disabled: true,
            };
        case 'wait':
            return { key: 'wait', label: t('Retry later'), disabled: true };
        default:
            return { key: 'retry', label: t('Retry') };
    }
}

export function primaryAction(
    run: DataProcessingJob,
    t: Translate,
): RecoveryAction | null {
    if (isActiveStatus(run.status)) {
        if (!run.abilities.cancel) {
            return null;
        }

        return {
            key: 'cancel',
            label: t('Stop'),
            confirm: run.status === 'processing',
        };
    }

    if (run.status === 'completed') {
        if (run.abilities.download && run.download_url) {
            return { key: 'download', label: t('Download') };
        }

        return null;
    }

    if (run.status === 'failed') {
        if (run.abilities.retry) {
            return failedPrimary(run, t);
        }

        return null;
    }

    // cancelled
    return run.abilities.retry ? { key: 'retry', label: t('Retry') } : null;
}

export function runActions(
    run: DataProcessingJob,
    t: Translate,
): RecoveryActions {
    const primary = primaryAction(run, t);
    const secondary: RecoveryAction[] = [];

    const push = (action: RecoveryAction): void => {
        if (action.key !== primary?.key) {
            secondary.push(action);
        }
    };

    if (!isActiveStatus(run.status) && run.abilities.resume) {
        push({ key: 'resume', label: t('Resume') });
    }

    if (!isActiveStatus(run.status) && run.abilities.retry) {
        push({ key: 'retry', label: t('Retry') });
    }

    if (!isActiveStatus(run.status) && run.abilities.duplicate) {
        push({ key: 'duplicate', label: t('Run again') });
    }

    if (
        !isActiveStatus(run.status) &&
        run.abilities.download &&
        run.download_url
    ) {
        push({ key: 'download', label: t('Download') });
    }

    if (run.abilities.delete) {
        push({
            key: 'delete',
            label: t('Delete'),
            confirm: true,
            destructive: true,
        });
    }

    return { primary, secondary };
}

export function cancelConfirmation(
    run: DataProcessingJob,
    t: Translate,
): { title: string; description: string } {
    if (run.status === 'processing') {
        return {
            title: t('Stop this run?'),
            description: t(
                'It will finish the current step and stop. Rows already imported are kept, and re-running is safe — no duplicates.',
            ),
        };
    }

    return {
        title: t('Stop this run?'),
        description: t(
            'It has not started yet and will be removed from the queue.',
        ),
    };
}

export function deleteConfirmation(
    run: DataProcessingJob,
    t: Translate,
): { title: string; description: string } {
    if (isActiveStatus(run.status)) {
        return {
            title: t('Delete while running?'),
            description: t(
                'The run stops immediately and is removed. Rows already imported are kept.',
            ),
        };
    }

    return {
        title: t('Delete job?'),
        description: t('":name" and its files will be permanently deleted.', {
            name: run.name,
        }),
    };
}
