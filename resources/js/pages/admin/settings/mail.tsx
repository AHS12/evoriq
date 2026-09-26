import { Head, useForm } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { SettingsForm } from '@/components/setting/settings-form';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { test, update } from '@/routes/admin/settings/mail';
import type { SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
};

export default function MailSettings({ group }: Props) {
    const { post, processing } = useForm({});
    const { t } = useTranslation();

    const sendTest = () => post(test.url());

    return (
        <>
            <Head title={t('Mail settings')} />

            <div className="space-y-6">
                <SettingsForm
                    group={group}
                    action={update.form()}
                    description={t(
                        'Configure the SMTP provider used to send email.',
                    )}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('Send a test email')}</CardTitle>
                        <CardDescription>
                            {t(
                                'Sends a test message to your account email using the settings above.',
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={sendTest}
                            disabled={processing}
                        >
                            {processing ? <Spinner /> : <Send />}
                            {t('Send test email')}
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
