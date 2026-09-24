import { Head } from '@inertiajs/react';
import { SettingsForm } from '@/components/setting/settings-form';
import { update } from '@/routes/admin/settings/notifications';
import type { SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
};

export default function NotificationSettings({ group }: Props) {
    return (
        <>
            <Head title="Notification settings" />
            <SettingsForm
                group={group}
                action={update.form()}
                description="Control in-app notifications and how long they are kept."
            />
        </>
    );
}
