import { Head } from '@inertiajs/react';
import { SettingsForm } from '@/components/setting/settings-form';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/admin/settings/notifications';
import type { SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
};

export default function NotificationSettings({ group }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('Notification settings')} />
            <SettingsForm
                group={group}
                action={update.form()}
                description={t(
                    'Control in-app notifications and how long they are kept.',
                )}
            />
        </>
    );
}
