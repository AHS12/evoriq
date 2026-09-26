import { Head } from '@inertiajs/react';
import { SettingsForm } from '@/components/setting/settings-form';
import { useTranslation } from '@/hooks/use-translation';
import { update } from '@/routes/admin/settings/general';
import type { SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
};

export default function GeneralSettings({ group }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('General settings')} />

            <SettingsForm
                group={group}
                action={update.form()}
                description={t('Basic workspace preferences.')}
            />
        </>
    );
}
