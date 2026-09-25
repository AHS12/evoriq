import { Form } from '@inertiajs/react';
import { SettingField } from '@/components/setting/setting-field';
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
import type { SettingGroup } from '@/types';

type FormDefinition = {
    action: string;
    method: 'get' | 'post' | 'put' | 'patch' | 'delete';
};

type Props = {
    group: SettingGroup;
    action: FormDefinition;
    description?: string;
};

export function SettingsForm({ group, action, description }: Props) {
    const { t } = useTranslation();

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t(group.label)}</CardTitle>
                            {description && (
                                <CardDescription>
                                    {t(description)}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent className="grid gap-6 sm:grid-cols-2">
                            {group.fields.map((field) => (
                                <SettingField
                                    key={field.key}
                                    field={field}
                                    error={errors[field.key]}
                                />
                            ))}
                        </CardContent>
                    </Card>

                    <div className="flex justify-end">
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            {t('Save changes')}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
