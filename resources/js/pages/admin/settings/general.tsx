import { Head } from '@inertiajs/react';
import { SettingsForm } from '@/components/setting/settings-form';
import { update } from '@/routes/admin/settings/general';
import type { SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
};

export default function GeneralSettings({ group }: Props) {
    return (
        <>
            <Head title="General settings" />

            <SettingsForm
                group={group}
                action={update.form()}
                description="Basic workspace preferences."
            />
        </>
    );
}
