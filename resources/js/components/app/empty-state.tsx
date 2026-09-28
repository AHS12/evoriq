import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: ReactNode;
    /** `compact` suits in-card, in-lane or in-sheet hosts (FND-06). */
    variant?: 'default' | 'compact';
    className?: string;
};

export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    variant = 'default',
    className,
}: Props) {
    const compact = variant === 'compact';

    return (
        <div
            data-slot="empty-state"
            data-variant={variant}
            className={cn(
                'flex flex-col items-center justify-center rounded-lg border border-dashed text-center',
                compact ? 'gap-2 p-6' : 'gap-3 p-10',
                className,
            )}
        >
            {Icon && (
                <div
                    className={cn(
                        'flex items-center justify-center rounded-full bg-muted',
                        compact ? 'size-8' : 'size-10',
                    )}
                >
                    <Icon
                        className={cn(
                            'text-muted-foreground',
                            compact ? 'size-4' : 'size-5',
                        )}
                    />
                </div>
            )}
            <div className="space-y-1">
                <p className="font-medium">{title}</p>
                {description && (
                    <p
                        className={cn(
                            'mx-auto max-w-sm text-muted-foreground',
                            compact ? 'text-xs' : 'text-sm',
                        )}
                    >
                        {description}
                    </p>
                )}
            </div>
            {action}
        </div>
    );
}
