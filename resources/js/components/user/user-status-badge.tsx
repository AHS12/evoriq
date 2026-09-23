import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { UserStatus } from '@/types';

type Props = {
    status?: UserStatus;
    label?: string;
    className?: string;
};

const variants: Record<UserStatus, 'default' | 'secondary' | 'destructive'> = {
    active: 'default',
    invited: 'secondary',
    suspended: 'destructive',
};

export function UserStatusBadge({
    status = 'active',
    label,
    className,
}: Props) {
    const variant = variants[status] ?? 'secondary';

    return (
        <Badge variant={variant} className={cn(className)}>
            {label ?? status}
        </Badge>
    );
}
