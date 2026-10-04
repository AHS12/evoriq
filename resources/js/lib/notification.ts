import {
    AlertTriangle,
    Bell,
    Clock,
    FileCheck2,
    FileX2,
    Megaphone,
    RefreshCw,
    RotateCw,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { NotificationItem, NotificationPriority } from '@/types';

const ICONS: Record<string, LucideIcon> = {
    megaphone: Megaphone,
    'user-plus': UserPlus,
    'file-check': FileCheck2,
    'file-x': FileX2,
    'refresh-cw': RefreshCw,
    'alert-triangle': AlertTriangle,
    'list-clock': Clock,
    'rotate-cw': RotateCw,
};

export function notificationIcon(notification: NotificationItem): LucideIcon {
    if (notification.icon && ICONS[notification.icon]) {
        return ICONS[notification.icon];
    }

    return Bell;
}

export const priorityClasses: Record<NotificationPriority, string> = {
    info: 'bg-muted text-muted-foreground',
    success: 'bg-success/10 text-success',
    warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    critical: 'bg-destructive/10 text-destructive',
};
