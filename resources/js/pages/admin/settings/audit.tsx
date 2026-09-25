import { Head } from '@inertiajs/react';
import { SettingsForm } from '@/components/setting/settings-form';
import { update } from '@/routes/admin/settings/audit';
import type { SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
};

export default function AuditSettings({ group }: Props) {
    return (
        <>
            <Head title="Audit log settings" />
            <SettingsForm
                group={group}
                action={update.form()}
                description="How long audit entries are kept per channel before they are pruned. Changes are recorded on the audit trail."
            />
        </>
    );
}
