import { router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import InputError from '@/components/input-error';
import { InlineAlert } from '@/components/feedback/inline-alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { useZodForm } from '@/hooks/use-zod-form';
import { postJson } from '@/lib/http';
import {
    connectionRotateKeySchema,
    type ConnectionRotateKeyValues,
} from '@/lib/schemas/connection';
import { toast } from '@/lib/toast';
import { rotateKey } from '@/routes/connections';
import type {
    Connection,
    ConnectionError,
    ConnectionVerification,
} from '@/types';

type Props = {
    connection: Connection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * CONN-05 — rotate the API key. The new key is verified server-side before it
 * replaces the stored credential, so a rejected key leaves the old one intact.
 */
export function RotateKeyDialog({ connection, open, onOpenChange }: Props) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Rotate API key')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Paste a new Clockify API key. It is verified before it replaces the current key.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                {open && connection && (
                    <RotateKeyForm
                        connection={connection}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function RotateKeyForm({
    connection,
    onDone,
}: {
    connection: Connection;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [error, setError] = useState<ConnectionError | null>(null);
    const [processing, setProcessing] = useState(false);

    const { form, errors, validate, setField } =
        useZodForm<ConnectionRotateKeyValues>(
            { api_key: '' },
            connectionRotateKeySchema,
            t,
        );

    const submit = async (event: FormEvent): Promise<void> => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            const result = await postJson<ConnectionVerification>(
                rotateKey.url(connection.id),
                form.data,
            );

            if (!result.ok) {
                setError(result.error);

                return;
            }

            toast.success(t('API key rotated.'));
            onDone();
            router.reload();
        } catch {
            setError({
                code: 'clockify_unknown',
                label: t('Rotation failed'),
                hint: t('The connection could not be verified.'),
                action: 'retry',
                retry_after: null,
            });
        } finally {
            setProcessing(false);
        }
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-5">
            <div className="grid gap-2">
                <Label htmlFor="connection-rotate-key">{t('API key')}</Label>
                <Input
                    id="connection-rotate-key"
                    type="password"
                    value={form.data.api_key}
                    onChange={(event) =>
                        setField('api_key', event.target.value)
                    }
                    autoComplete="off"
                    spellCheck={false}
                    aria-invalid={Boolean(errors.api_key)}
                    autoFocus
                />
                <InputError message={errors.api_key} />
            </div>

            <p className="text-xs text-muted-foreground">
                {t('Your API key is stored encrypted and never shown again.')}
            </p>

            {error && (
                <InlineAlert
                    tone="error"
                    title={error.label ? t(error.label) : t('Rotation failed')}
                >
                    {error.hint ? t(error.hint) : null}
                </InlineAlert>
            )}

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onDone}
                    disabled={processing}
                >
                    {t('Cancel')}
                </Button>
                <Button type="submit" loading={processing}>
                    {t('Rotate key')}
                </Button>
            </div>
        </form>
    );
}
