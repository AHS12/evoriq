import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
};

/** Titled frame for a chart (or any data-viz panel). */
export function ChartCard({
    title,
    description,
    actions,
    children,
    className,
    contentClassName,
}: Props) {
    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div className="space-y-1">
                    <CardTitle className="text-base">{title}</CardTitle>
                    {description ? (
                        <CardDescription>{description}</CardDescription>
                    ) : null}
                </div>
                {actions}
            </CardHeader>
            <CardContent className={cn('pt-2', contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
