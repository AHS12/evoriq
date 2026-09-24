import { BellOff } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';

type Props = {
    className?: string;
    description?: string;
};

export function NotificationEmpty({ className, description }: Props) {
    return (
        <EmptyState
            icon={BellOff}
            title="You're all caught up"
            description={description ?? 'New notifications will appear here.'}
            className={className}
        />
    );
}
