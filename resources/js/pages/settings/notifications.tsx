import { Head } from '@inertiajs/react';
import { NotificationPreferenceForm } from '@/components/notification/notification-preference-form';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/notification-preferences';
import type { NotificationPreferences, NotificationTypeOption } from '@/types';

type Props = {
    preferences: NotificationPreferences;
    types: NotificationTypeOption[];
};

export default function NotificationSettings({ preferences, types }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Notifications')} />
            <NotificationPreferenceForm
                preferences={preferences}
                types={types}
                action={update.form()}
            />
        </>
    );
}
