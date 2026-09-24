import { Link, router } from '@inertiajs/react';
import { X } from 'lucide-react';
import type { MouseEvent } from 'react';
import { notificationIcon, priorityClasses } from '@/lib/notification';
import { cn } from '@/lib/utils';
import { destroy, read } from '@/routes/notifications';
import type { NotificationItem } from '@/types';

type Props = {
    notification: NotificationItem;
    compact?: boolean;
};

export function NotificationItemRow({ notification, compact = false }: Props) {
    const Icon = notificationIcon(notification);

    const markRead = (): void => {
        if (notification.is_read) {
            return;
        }

        router.post(
            read.url(notification.id),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const dismiss = (event: MouseEvent): void => {
        event.preventDefault();
        event.stopPropagation();

        router.delete(destroy.url(notification.id), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const content = (
        <div
            className={cn(
                'flex gap-3 transition-colors',
                compact ? 'px-4 py-3' : 'px-5 py-4',
                !notification.is_read && 'bg-accent/40',
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full',
                    priorityClasses[notification.priority],
                )}
            >
                <Icon className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <div className="flex items-start justify-between gap-2">
                    <p className="truncate text-sm font-medium">
                        {notification.title}
                    </p>
                    {!notification.is_read && (
                        <span className="mt-1.5 size-2 shrink-0 rounded-full bg-primary" />
                    )}
                </div>
                {notification.body && (
                    <p
                        className={cn(
                            'mt-0.5 text-sm text-muted-foreground',
                            compact && 'line-clamp-2',
                        )}
                    >
                        {notification.body}
                    </p>
                )}
                <p className="mt-1 text-xs text-muted-foreground">
                    {notification.created_at_diff}
                </p>
            </div>
        </div>
    );

    return (
        <div className="group relative">
            {notification.action_url ? (
                <Link
                    href={notification.action_url}
                    onClick={markRead}
                    className="block"
                >
                    {content}
                </Link>
            ) : (
                <button
                    type="button"
                    onClick={markRead}
                    className="block w-full text-left"
                >
                    {content}
                </button>
            )}

            <button
                type="button"
                onClick={dismiss}
                aria-label="Dismiss notification"
                className="absolute top-2 right-2 hidden rounded-md p-1 text-muted-foreground group-hover:block hover:bg-accent hover:text-foreground"
            >
                <X className="size-3.5" />
            </button>
        </div>
    );
}
