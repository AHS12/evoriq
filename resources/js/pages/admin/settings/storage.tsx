import { Form, router } from '@inertiajs/react';
import { PlugZap } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/hooks/use-translation';
import { test, update } from '@/routes/admin/settings/storage';
import type { RemoteStorage } from '@/types';

type Props = {
    storage: RemoteStorage;
    configured: boolean;
};

type FormValues = {
    enabled: boolean;
    provider: string;
    access_key_id: string;
    secret_access_key: string;
    region: string;
    bucket: string;
    endpoint: string;
    use_path_style: boolean;
};

const providerPresets: Record<string, { region: string; pathStyle: boolean }> =
    {
        s3: { region: 'us-east-1', pathStyle: false },
        r2: { region: 'auto', pathStyle: true },
        custom: { region: 'us-east-1', pathStyle: true },
    };

export default function StorageSettings({ storage, configured }: Props) {
    const { t } = useTranslation();
    const [testing, setTesting] = useState(false);

    const [values, setValues] = useState<FormValues>({
        enabled: storage.enabled,
        provider: storage.provider,
        access_key_id: storage.access_key_id,
        secret_access_key: '',
        region: storage.region,
        bucket: storage.bucket,
        endpoint: storage.endpoint ?? '',
        use_path_style: storage.use_path_style,
    });

    const set = <K extends keyof FormValues>(key: K, value: FormValues[K]) =>
        setValues((current) => ({ ...current, [key]: value }));

    const applyProvider = (provider: string) => {
        const preset = providerPresets[provider];

        setValues((current) => ({
            ...current,
            provider,
            region: preset.region,
            use_path_style: preset.pathStyle,
        }));
    };

    const runTest = () => {
        setTesting(true);

        router.post(
            test.url(),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            },
        );
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                <div className="space-y-1.5">
                    <CardTitle>{t('Remote storage')}</CardTitle>
                    <CardDescription>
                        {t(
                            'S3-compatible object storage (AWS S3, Cloudflare R2, ...) used as an off-site backup destination. Credentials are stored in the environment file.',
                        )}
                    </CardDescription>
                </div>
                <div className="flex items-center gap-2">
                    {configured && (
                        <span className="text-xs text-success">
                            {t('Configured')}
                        </span>
                    )}
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={testing}
                        onClick={runTest}
                    >
                        {testing && <Spinner />}
                        <PlugZap className="size-4" />
                        {t('Test connection')}
                    </Button>
                </div>
            </CardHeader>
            <CardContent>
                <Form {...update.form()} noValidate className="space-y-6">
                    {({ processing, errors }) => (
                        <>
                            {/* Hidden inputs keep the controlled switches and
                                selects part of the submitted payload. */}
                            <input
                                type="hidden"
                                name="enabled"
                                value={values.enabled ? '1' : '0'}
                            />
                            <input
                                type="hidden"
                                name="provider"
                                value={values.provider}
                            />
                            <input
                                type="hidden"
                                name="use_path_style"
                                value={values.use_path_style ? '1' : '0'}
                            />

                            <div className="flex items-center justify-between gap-4 rounded-lg border p-3">
                                <div>
                                    <p className="text-sm font-medium">
                                        {t('Enable remote storage')}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'Offer the remote disk as a backup destination.',
                                        )}
                                    </p>
                                </div>
                                <Switch
                                    checked={values.enabled}
                                    onCheckedChange={(checked) =>
                                        set('enabled', checked)
                                    }
                                    aria-label={t('Enable remote storage')}
                                />
                            </div>
                            <InputError message={errors.enabled} />

                            <div className="grid gap-6 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="provider">
                                        {t('Provider')}
                                    </Label>
                                    <Select
                                        value={values.provider}
                                        onValueChange={(value) => {
                                            applyProvider(value);
                                        }}
                                    >
                                        <SelectTrigger id="provider">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="s3">
                                                {t('Amazon S3')}
                                            </SelectItem>
                                            <SelectItem value="r2">
                                                {t('Cloudflare R2')}
                                            </SelectItem>
                                            <SelectItem value="custom">
                                                {t('Custom S3-compatible')}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.provider} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="access_key_id">
                                        {t('Access key ID')}
                                    </Label>
                                    <Input
                                        id="access_key_id"
                                        name="access_key_id"
                                        value={values.access_key_id}
                                        onChange={(event) =>
                                            set(
                                                'access_key_id',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.access_key_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="secret_access_key">
                                        {t('Secret access key')}
                                    </Label>
                                    <Input
                                        id="secret_access_key"
                                        name="secret_access_key"
                                        type="password"
                                        autoComplete="new-password"
                                        placeholder={
                                            storage.has_secret
                                                ? t(
                                                      'Leave blank to keep the stored key',
                                                  )
                                                : ''
                                        }
                                        value={values.secret_access_key}
                                        onChange={(event) =>
                                            set(
                                                'secret_access_key',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.secret_access_key}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="region">
                                        {t('Region')}
                                    </Label>
                                    <Input
                                        id="region"
                                        name="region"
                                        value={values.region}
                                        onChange={(event) =>
                                            set('region', event.target.value)
                                        }
                                    />
                                    <InputError message={errors.region} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="bucket">
                                        {t('Bucket')}
                                    </Label>
                                    <Input
                                        id="bucket"
                                        name="bucket"
                                        value={values.bucket}
                                        onChange={(event) =>
                                            set('bucket', event.target.value)
                                        }
                                    />
                                    <InputError message={errors.bucket} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="endpoint">
                                        {t('Endpoint')}
                                    </Label>
                                    <Input
                                        id="endpoint"
                                        name="endpoint"
                                        placeholder={t(
                                            'Leave empty for AWS S3',
                                        )}
                                        value={values.endpoint}
                                        onChange={(event) =>
                                            set('endpoint', event.target.value)
                                        }
                                    />
                                    <InputError message={errors.endpoint} />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Switch
                                    id="use_path_style"
                                    checked={values.use_path_style}
                                    onCheckedChange={(checked) =>
                                        set('use_path_style', checked)
                                    }
                                />
                                <Label
                                    htmlFor="use_path_style"
                                    className="font-normal"
                                >
                                    {t(
                                        'Use a path-style endpoint (required for Cloudflare R2)',
                                    )}
                                </Label>
                            </div>
                            <InputError message={errors.use_path_style} />

                            <div className="flex justify-end">
                                <Button disabled={processing}>
                                    {processing && <Spinner />}
                                    {t('Save changes')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
