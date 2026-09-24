import { NotificationItemRow } from '@/components/notification/notification-item';
import type { NotificationItem } from '@/types';

type Props = {
    notifications: NotificationItem[];
    compact?: boolean;
};

export function NotificationList({ notifications, compact = false }: Props) {
    return (
        <div className="divide-y">
            {notifications.map((notification) => (
                <NotificationItemRow
                    key={notification.id}
                    notification={notification}
                    compact={compact}
                />
            ))}
        </div>
    );
}
