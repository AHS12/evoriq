import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { PermissionMatrix } from '@/components/role/permission-matrix';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useZodForm } from '@/hooks/use-zod-form';
import { roleFormSchema } from '@/lib/schemas/role';
import { store, update } from '@/routes/roles';
import type { Permission, Role } from '@/types';

type Props = {
    permissions: Permission[];
    role?: Role;
    onCancel: () => void;
    onSuccess?: () => void;
};

export function RoleForm({ permissions, role, onCancel, onSuccess }: Props) {
    const isSuperAdmin = role?.name === 'Super Admin';
    const nameLocked = role?.is_system ?? false;

    const { form, errors, validate, setField } = useZodForm(
        {
            name: role?.name ?? '',
            permissions: role?.permissions ?? [],
        },
        roleFormSchema,
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!validate()) {
            return;
        }

        const options = { onSuccess: () => onSuccess?.() };

        if (role) {
            form.patch(update.url(role.id), options);
        } else {
            form.post(store.url(), options);
        }
    };

    return (
        <form
            onSubmit={submit}
            noValidate
            className="flex min-h-0 flex-1 flex-col"
        >
            <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
                <div className="grid gap-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) =>
                            setField('name', event.target.value)
                        }
                        placeholder="e.g. Project Manager"
                        disabled={nameLocked}
                        aria-invalid={Boolean(errors.name)}
                        autoFocus={!nameLocked}
                        required
                    />
                    <InputError message={errors.name} />
                    {nameLocked && (
                        <p className="text-xs text-muted-foreground">
                            System role names cannot be changed.
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label>Permissions</Label>
                    <PermissionMatrix
                        permissions={permissions}
                        value={form.data.permissions}
                        onChange={(value) => setField('permissions', value)}
                        readOnly={isSuperAdmin}
                        allowSystemPermissions={role?.is_system ?? false}
                    />
                    <InputError message={errors.permissions} />
                </div>
            </div>

            <div className="flex shrink-0 justify-end gap-3 border-t bg-background px-6 py-4">
                <Button
                    type="button"
                    variant="ghost"
                    onClick={onCancel}
                    disabled={form.processing}
                >
                    {isSuperAdmin ? 'Close' : 'Cancel'}
                </Button>
                {!isSuperAdmin && (
                    <Button type="submit" disabled={form.processing}>
                        {form.processing && <Spinner />}
                        {role ? 'Save changes' : 'Create role'}
                    </Button>
                )}
            </div>
        </form>
    );
}
