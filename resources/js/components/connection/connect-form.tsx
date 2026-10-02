import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { InlineAlert } from '@/components/feedback/inline-alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { useZodForm } from '@/hooks/use-zod-form';
import { postJson } from '@/lib/http';
import {
    connectionKeySchema,
    type ConnectionKeyValues,
} from '@/lib/schemas/connection';
import { verify } from '@/routes/connections';
import type {
    ConnectionError,
    ConnectionVerification,
    RegionOption,
} from '@/types';

export type ConnectionCredentials = {
    name: string;
    api_key: string;
    region: string;
};

type Props = {
    regions: RegionOption[];
    onVerified: (
        result: ConnectionVerification,
        credentials: ConnectionCredentials,
    ) => void;
};

const CLOCKIFY_KEYS_URL = 'https://app.clockify.me/user/settings';

/**
 * CONN-04 — the connect key step. The key is submitted once, never re-rendered,
 * and never logged; errors render the mapped `ApiErrorCode` label/hint.
 */
export function ConnectForm({ regions, onVerified }: Props) {
    const { t } = useTranslation();
    const [verifying, setVerifying] = useState(false);
    const [error, setError] = useState<ConnectionError | null>(null);

    const { form, errors, validate, setField } =
        useZodForm<ConnectionKeyValues>(
            {
                name: '',
                api_key: '',
                region: regions[0]?.value ?? 'global',
            },
            connectionKeySchema,
            t,
        );

    const runVerify = async (): Promise<void> => {
        if (!validate()) {
            return;
        }

        setVerifying(true);
        setError(null);

        try {
            const result = await postJson<ConnectionVerification>(
                verify.url(),
                form.data,
            );

            if (result.ok) {
                onVerified(result, {
                    name: form.data.name,
                    api_key: form.data.api_key,
                    region: form.data.region,
                });

                return;
            }

            setError(result.error);
        } catch {
            setError({
                code: 'clockify_unknown',
                label: t('Verification failed'),
                hint: t('The connection could not be verified.'),
                action: 'retry',
                retry_after: null,
            });
        } finally {
            setVerifying(false);
        }
    };

    const submit = (event: FormEvent): void => {
        event.preventDefault();
        void runVerify();
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-5">
            <div className="grid gap-2">
                <Label htmlFor="connection-name">{t('Name (optional)')}</Label>
                <Input
                    id="connection-name"
                    value={form.data.name}
                    onChange={(event) => setField('name', event.target.value)}
                    autoComplete="off"
                    aria-invalid={Boolean(errors.name)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="connection-api-key">{t('API key')}</Label>
                <Input
                    id="connection-api-key"
                    type="password"
                    value={form.data.api_key}
                    onChange={(event) =>
                        setField('api_key', event.target.value)
                    }
                    autoComplete="off"
                    spellCheck={false}
                    aria-invalid={Boolean(errors.api_key)}
                />
                <InputError message={errors.api_key} />
                <a
                    href={CLOCKIFY_KEYS_URL}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    {t('Where do I find my API key?')}
                </a>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="connection-region">{t('Region')}</Label>
                <Select
                    value={form.data.region}
                    onValueChange={(value) => setField('region', value)}
                >
                    <SelectTrigger id="connection-region" className="w-full">
                        <SelectValue placeholder={t('Region')} />
                    </SelectTrigger>
                    <SelectContent>
                        {regions.map((region) => (
                            <SelectItem key={region.value} value={region.value}>
                                {t(region.label)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.region} />
            </div>

            <p className="text-xs text-muted-foreground">
                {t('Your API key is stored encrypted and never shown again.')}
            </p>

            {error && (
                <InlineAlert
                    tone="error"
                    title={
                        error.label ? t(error.label) : t('Verification failed')
                    }
                    action={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => void runVerify()}
                        >
                            {t('Retry')}
                        </Button>
                    }
                >
                    {error.hint ? t(error.hint) : null}
                    {error.retry_after != null && (
                        <span className="block text-xs">
                            {t('Try again in :seconds seconds.', {
                                seconds: error.retry_after,
                            })}
                        </span>
                    )}
                </InlineAlert>
            )}

            <Button type="submit" loading={verifying}>
                {t('Verify')}
            </Button>
        </form>
    );
}
