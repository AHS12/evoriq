import { type FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import {
    connectionRenameSchema,
    type ConnectionRenameValues,
} from '@/lib/schemas/connection';
import { update } from '@/routes/connections';
import type { Connection } from '@/types';

type Props = {
    connection: Connection | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/**
 * CONN-05 — rename a connection. The form mounts only while the dialog is open
 * so `useForm` state resets each time.
 */
export function RenameConnectionDialog({
    connection,
    open,
    onOpenChange,
}: Props) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{t('Rename connection')}</DialogTitle>
                    <DialogDescription>
                        {t('Give this connection a recognizable name.')}
                    </DialogDescription>
                </DialogHeader>

                {open && connection && (
                    <RenameForm
                        connection={connection}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function RenameForm({
    connection,
    onDone,
}: {
    connection: Connection;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const { form, errors, validate, setField } =
        useZodForm<ConnectionRenameValues>(
            { name: connection.name },
            connectionRenameSchema,
            t,
        );

    const submit = (event: FormEvent): void => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        form.put(update.url(connection.id), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-5">
            <div className="grid gap-2">
                <Label htmlFor="connection-rename">{t('Name')}</Label>
                <Input
                    id="connection-rename"
                    value={form.data.name}
                    onChange={(event) => setField('name', event.target.value)}
                    aria-invalid={Boolean(errors.name)}
                    autoFocus
                />
                <InputError message={errors.name} />
            </div>

            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onDone}
                    disabled={form.processing}
                >
                    {t('Cancel')}
                </Button>
                <Button type="submit" loading={form.processing}>
                    {t('Save')}
                </Button>
            </div>
        </form>
    );
}
