import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

export type InlineAlertTone = 'info' | 'success' | 'warning' | 'error';

type ToneConfig = {
    icon: LucideIcon;
    container: string;
    iconClassName: string;
};

const TONES: Record<InlineAlertTone, ToneConfig> = {
    info: {
        icon: Info,
        container: 'border-info/30 bg-info/10',
        iconClassName: 'text-info',
    },
    success: {
        icon: CircleCheck,
        container: 'border-success/30 bg-success/10',
        iconClassName: 'text-success',
    },
    warning: {
        icon: TriangleAlert,
        container: 'border-warning/40 bg-warning/10',
        iconClassName: 'text-warning',
    },
    error: {
        icon: CircleAlert,
        container: 'border-destructive/30 bg-destructive/10',
        iconClassName: 'text-destructive',
    },
};

type Props = {
    /** Message tone; default `info`. */
    tone?: InlineAlertTone;
    title?: string;
    /** Description / rich content. */
    children?: ReactNode;
    /** Override the tone's default icon. */
    icon?: LucideIcon;
    /** Primary control (e.g. a Resume button). */
    action?: ReactNode;
    /** When provided, renders a dismiss button. */
    onDismiss?: () => void;
    className?: string;
};

/**
 * FND-06 — a tone-aware inline message for contextual notices inside a working
 * surface. Use it for "polling paused", "showing cached data", "backing off";
 * use `ErrorState` when a whole region failed to load.
 */
export function InlineAlert({
    tone = 'info',
    title,
    children,
    icon,
    action,
    onDismiss,
    className,
}: Props) {
    const { t } = useTranslation();
    const config = TONES[tone];
    const Icon = icon ?? config.icon;
    const assertive = tone === 'error' || tone === 'warning';

    return (
        <div
            data-slot="inline-alert"
            data-tone={tone}
            role={assertive ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-3 rounded-lg border px-3 py-2.5 text-sm',
                config.container,
                className,
            )}
        >
            <Icon
                aria-hidden
                className={cn('mt-0.5 size-4 shrink-0', config.iconClassName)}
            />

            <div className="min-w-0 flex-1 space-y-1">
                {title && <p className="leading-none font-medium">{title}</p>}
                {children && (
                    <div className="text-sm text-muted-foreground">
                        {children}
                    </div>
                )}
            </div>

            {action}

            {onDismiss && (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="-m-1 size-7 shrink-0"
                    onClick={onDismiss}
                    aria-label={t('Dismiss')}
                >
                    <X />
                </Button>
            )}
        </div>
    );
}
