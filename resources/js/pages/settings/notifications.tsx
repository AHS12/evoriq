import { Head } from '@inertiajs/react';
import { NotificationPreferenceForm } from '@/components/notification/notification-preference-form';
import { update } from '@/routes/notification-preferences';
import type { NotificationPreferences, NotificationTypeOption } from '@/types';

type Props = {
    preferences: NotificationPreferences;
    types: NotificationTypeOption[];
};

export default function NotificationSettings({ preferences, types }: Props) {
    return (
        <>
            <Head title="Notifications" />
            <NotificationPreferenceForm
                preferences={preferences}
                types={types}
                action={update.form()}
            />
        </>
    );
}
