import { usePage } from '@inertiajs/react';
import type { NotificationSummary } from '@/types';

const FALLBACK: NotificationSummary = {
    unread_count: 0,
    recent: [],
    preferences: {
        inapp: true,
        sound: true,
        desktop: false,
        muted_types: [],
    },
};

export function useNotifications(): NotificationSummary {
    const { notifications } = usePage().props;

    return notifications ?? FALLBACK;
}
