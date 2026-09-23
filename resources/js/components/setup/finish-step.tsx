import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type {
    AccountFormData,
    AccountFormErrors,
    ExistingAdmin,
} from '@/types/setup';

type Props = {
    data: AccountFormData;
    errors: AccountFormErrors;
    processing: boolean;
    hasSuperAdmin: boolean;
    existingAdmin: ExistingAdmin | null;
    onAppNameChange: (value: string) => void;
    onBack: () => void;
    onSubmit: () => void;
};

export function FinishStep({
    data,
    errors,
    processing,
    hasSuperAdmin,
    existingAdmin,
    onAppNameChange,
    onBack,
    onSubmit,
}: Props) {
    return (
        <div className="space-y-4">
            <div className="space-y-1">
                <h2 className="text-xl font-semibold">Ready to go</h2>
                <p className="text-sm text-muted-foreground">
                    {hasSuperAdmin
                        ? "We'll finish setup using your existing super administrator account."
                        : "We'll create your super administrator account and sign you in."}
                </p>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="app_name_finish">Workspace name</Label>
                <Input
                    id="app_name_finish"
                    value={data.app_name}
                    onChange={(event) => onAppNameChange(event.target.value)}
                    aria-invalid={Boolean(errors.app_name)}
                />
                <InputError message={errors.app_name} />
            </div>

            <dl className="divide-y rounded-lg border text-sm">
                {hasSuperAdmin && existingAdmin ? (
                    <>
                        <SummaryRow
                            label="Administrator"
                            value={existingAdmin.name}
                        />
                        <SummaryRow label="Email" value={existingAdmin.email} />
                    </>
                ) : (
                    <>
                        <SummaryRow label="Name" value={data.name} />
                        <SummaryRow label="Email" value={data.email} />
                    </>
                )}
            </dl>

            <div className="flex justify-between">
                <Button
                    variant="outline"
                    onClick={onBack}
                    disabled={processing}
                >
                    Back
                </Button>
                <Button onClick={onSubmit} disabled={processing}>
                    {processing && <Spinner />}
                    Complete setup
                </Button>
            </div>
        </div>
    );
}

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between p-3">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}
