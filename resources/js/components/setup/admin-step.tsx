import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    AccountField,
    AccountFormData,
    AccountFormErrors,
} from '@/types/setup';

type Props = {
    data: AccountFormData;
    errors: AccountFormErrors;
    processing: boolean;
    onChange: (field: AccountField, value: string) => void;
    onBack: () => void;
    onNext: () => void;
};

export function AdminStep({
    data,
    errors,
    processing,
    onChange,
    onBack,
    onNext,
}: Props) {
    return (
        <div className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Super administrator</h2>
                <p className="text-sm text-muted-foreground">
                    This account has full access. Additional users are created
                    by you later.
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="app_name">Workspace name</Label>
                <Input
                    id="app_name"
                    value={data.app_name}
                    onChange={(event) =>
                        onChange('app_name', event.target.value)
                    }
                    aria-invalid={Boolean(errors.app_name)}
                />
                <InputError message={errors.app_name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Full name</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(event) => onChange('name', event.target.value)}
                    autoComplete="name"
                    aria-invalid={Boolean(errors.name)}
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(event) => onChange('email', event.target.value)}
                    autoComplete="email"
                    aria-invalid={Boolean(errors.email)}
                />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">Password</Label>
                <PasswordInput
                    id="password"
                    value={data.password}
                    onChange={(event) =>
                        onChange('password', event.target.value)
                    }
                    autoComplete="new-password"
                    aria-invalid={Boolean(errors.password)}
                />
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    value={data.password_confirmation}
                    onChange={(event) =>
                        onChange('password_confirmation', event.target.value)
                    }
                    autoComplete="new-password"
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <div className="flex justify-between">
                <Button
                    variant="outline"
                    onClick={onBack}
                    disabled={processing}
                >
                    Back
                </Button>
                <Button onClick={onNext} disabled={processing}>
                    Continue
                </Button>
            </div>
        </div>
    );
}
