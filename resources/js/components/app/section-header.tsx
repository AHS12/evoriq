import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
};

export function SectionHeader({
    title,
    description,
    actions,
    className,
}: Props) {
    return (
        <div
            className={cn('flex items-center justify-between gap-4', className)}
        >
            <div className="space-y-0.5">
                <h2 className="text-base font-medium">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {actions}
        </div>
    );
}
