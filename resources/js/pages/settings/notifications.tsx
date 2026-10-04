import { Head } from '@inertiajs/react';
import { NotificationPreferenceForm } from '@/components/notification/notification-preference-form';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/notification-preferences';
import type {
    NotificationCategoryOption,
    NotificationPreferences,
    NotificationTypeOption,
} from '@/types';

type Props = {
    preferences: NotificationPreferences;
    types: NotificationTypeOption[];
    categories: NotificationCategoryOption[];
};

export default function NotificationSettings({
    preferences,
    types,
    categories,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Notifications')} />
            <NotificationPreferenceForm
                preferences={preferences}
                types={types}
                categories={categories}
                action={update.form()}
            />
        </>
    );
}
