import { CircleAlert } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    /** Defaults to a circled alert. */
    icon?: LucideIcon;
    /** Primary next step: retry the failed region. */
    onRetry?: () => void;
    /** Overrides the default `Retry` label. */
    retryLabel?: string;
    /** Shows the retry button's pending state. */
    retrying?: boolean;
    /** Raw error message, revealed behind a disclosure. */
    details?: string;
    /** Secondary control beside Retry. */
    action?: ReactNode;
    /** Tighter layout for in-card/in-lane use. */
    compact?: boolean;
    className?: string;
};

/**
 * FND-06 — a block-level failure state with a next step. Render it whenever a
 * region's data could not load; never leave a failed region blank. Pair the
 * message with a retry so the user is never stuck.
 */
export function ErrorState({
    title,
    description,
    icon: Icon = CircleAlert,
    onRetry,
    retryLabel,
    retrying = false,
    details,
    action,
    compact = false,
    className,
}: Props) {
    const { t } = useTranslation();

    return (
        <div
            data-slot="error-state"
            role="alert"
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-lg border border-destructive/30 bg-destructive/5 text-center',
                compact ? 'p-4' : 'p-8',
                className,
            )}
        >
            <div
                className={cn(
                    'flex items-center justify-center rounded-full bg-destructive/10',
                    compact ? 'size-8' : 'size-10',
                )}
            >
                <Icon
                    aria-hidden
                    className={cn(
                        'text-destructive',
                        compact ? 'size-4' : 'size-5',
                    )}
                />
            </div>

            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                {description && (
                    <p className="mx-auto max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {(onRetry || action) && (
                <div className="flex flex-wrap items-center justify-center gap-2">
                    {onRetry && (
                        <Button
                            type="button"
                            variant="outline"
                            size={compact ? 'sm' : 'default'}
                            loading={retrying}
                            onClick={onRetry}
                        >
                            {retryLabel ?? t('Retry')}
                        </Button>
                    )}
                    {action}
                </div>
            )}

            {details && (
                <details className="group w-full max-w-md text-left">
                    <summary className="cursor-pointer text-xs text-muted-foreground select-none">
                        <span className="group-open:hidden">
                            {t('Show details')}
                        </span>
                        <span className="hidden group-open:inline">
                            {t('Hide details')}
                        </span>
                    </summary>
                    <pre className="mt-2 overflow-x-auto rounded-md bg-muted p-2 text-xs whitespace-pre-wrap text-muted-foreground">
                        {details}
                    </pre>
                </details>
            )}
        </div>
    );
}
