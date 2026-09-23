import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

export function SystemRoleBadge({ className }: Props) {
    return (
        <Badge variant="secondary" className={cn('gap-1', className)}>
            <Lock className="size-3" />
            System
        </Badge>
    );
}
