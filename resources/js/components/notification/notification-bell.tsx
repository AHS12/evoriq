import { Link, router } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { NotificationEmpty } from '@/components/notification/notification-empty';
import { NotificationList } from '@/components/notification/notification-list';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { useNotificationPoll } from '@/hooks/use-notification-poll';
import { useNotifications } from '@/hooks/use-notifications';
import { index as notificationsIndex, readAll } from '@/routes/notifications';

export function NotificationBell() {
    const { unread_count, recent } = useNotifications();
    useNotificationPoll();
    const [open, setOpen] = useState(false);

    const markAllRead = (): void => {
        router.post(
            readAll.url(),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative size-9"
                    aria-label="Notifications"
                >
                    <Bell className="size-5" />
                    {unread_count > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-medium text-primary-foreground">
                            {unread_count > 99 ? '99+' : unread_count}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-96 p-0">
                <div className="flex items-center justify-between px-4 py-3">
                    <p className="text-sm font-medium">Notifications</p>
                    {unread_count > 0 && (
                        <Button variant="ghost" size="sm" onClick={markAllRead}>
                            Mark all read
                        </Button>
                    )}
                </div>
                <Separator />
                <div className="max-h-[70vh] overflow-y-auto">
                    {recent.length === 0 ? (
                        <NotificationEmpty className="rounded-none border-0" />
                    ) : (
                        <NotificationList notifications={recent} compact />
                    )}
                </div>
                <Separator />
                <div className="p-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        asChild
                        className="w-full justify-center"
                    >
                        <Link
                            href={notificationsIndex()}
                            onClick={() => setOpen(false)}
                        >
                            View all
                        </Link>
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
