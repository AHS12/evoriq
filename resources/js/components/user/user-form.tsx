import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { RoleSelect } from '@/components/user/role-select';
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
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { useZodForm } from '@/hooks/use-zod-form';
import { userFormSchema } from '@/lib/schemas/user';
import { store, update } from '@/routes/users';
import type { User, UserStatus } from '@/types';

type StatusOption = {
    value: UserStatus;
    label: string;
};

type Props = {
    roles: string[];
    statuses?: StatusOption[];
    user?: User;
    onCancel: () => void;
    onSuccess?: () => void;
};

export function UserForm({
    roles,
    statuses = [],
    user,
    onCancel,
    onSuccess,
}: Props) {
    const { t } = useTranslation();

    const { form, errors, validate, setField } = useZodForm(
        {
            name: user?.name ?? '',
            email: user?.email ?? '',
            roles: user?.roles ?? [],
            status: user?.status ?? 'active',
        },
        userFormSchema,
        t,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        const options = { onSuccess: () => onSuccess?.() };

        if (user) {
            form.patch(update.url(user.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form onSubmit={submit} noValidate className="space-y-5">
            <div className="grid gap-2">
                <Label htmlFor="name">{t('Name')}</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) => setField('name', event.target.value)}
                    autoComplete="name"
                    placeholder={t('Full name')}
                    aria-invalid={Boolean(errors.name)}
                    autoFocus
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">{t('Email address')}</Label>
                <Input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(event) => setField('email', event.target.value)}
                    autoComplete="email"
                    placeholder="name@example.com"
                    aria-invalid={Boolean(errors.email)}
                    required
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="roles">{t('Roles')}</Label>
                <RoleSelect
                    id="roles"
                    roles={roles}
                    value={form.data.roles}
                    onChange={(value) => setField('roles', value)}
                />
                <InputError message={errors.roles} />
            </div>

            {user && statuses.length > 0 && (
                <div className="grid gap-2">
                    <Label htmlFor="status">{t('Status')}</Label>
                    <Select
                        value={form.data.status}
                        onValueChange={(value) =>
                            setField('status', value as UserStatus)
                        }
                    >
                        <SelectTrigger id="status" className="w-full">
                            <SelectValue placeholder={t('Select a status')} />
                        </SelectTrigger>
                        <SelectContent>
                            {statuses.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.status} />
                </div>
            )}

            {!user && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'An invitation email will be sent so the user can set their own password.',
                    )}
                </p>
            )}

            <div className="flex justify-end gap-3 pt-2">
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onCancel}
                    disabled={form.processing}
                >
                    {t('Cancel')}
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {user ? t('Save changes') : t('Create user')}
                </Button>
            </div>
        </form>
    );
}
