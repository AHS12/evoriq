import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    label: string;
    value: string;
    description?: string;
    icon?: LucideIcon;
    className?: string;
};

export function StatCard({
    label,
    value,
    description,
    icon: Icon,
    className,
}: Props) {
    return (
        <Card className={className}>
            <CardContent className="flex items-start justify-between gap-4">
                <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold tracking-tight">
                        {value}
                    </p>
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {Icon && (
                    <div className="flex size-9 items-center justify-center rounded-lg bg-muted">
                        <Icon className="size-4 text-muted-foreground" />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
