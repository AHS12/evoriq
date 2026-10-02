import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ConnectForm,
    type ConnectionCredentials,
} from '@/components/connection/connect-form';
import { ConnectionResult } from '@/components/connection/connection-result';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/connections';
import type { ConnectionVerification, RegionOption } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    regions: RegionOption[];
};

/**
 * CONN-04 — the "Add connection" dialog. Key → verify → workspace → save, with
 * the form mounted only while open so `useForm` state resets each time.
 */
export function ConnectDialog({ open, onOpenChange, regions }: Props) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('Connect Clockify')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Connect Clockify to import and analyze your time data.',
                        )}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <ConnectFlow
                        regions={regions}
                        onDone={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

function ConnectFlow({
    regions,
    onDone,
}: {
    regions: RegionOption[];
    onDone: () => void;
}) {
    const [result, setResult] = useState<ConnectionVerification | null>(null);
    const [credentials, setCredentials] =
        useState<ConnectionCredentials | null>(null);
    const [selected, setSelected] = useState<string | null>(null);

    const form = useForm({
        name: '',
        api_key: '',
        region: 'global',
        subdomain: '',
        clockify_id: '',
    });

    const onVerified = (
        verified: ConnectionVerification,
        next: ConnectionCredentials,
    ): void => {
        setResult(verified);
        setCredentials(next);
        setSelected(
            verified.workspaces.find((workspace) => workspace.active)
                ?.clockify_id ??
                verified.workspaces[0]?.clockify_id ??
                null,
        );
    };

    const reset = (): void => {
        setResult(null);
        setCredentials(null);
        setSelected(null);
    };

    const confirm = (): void => {
        if (!credentials || !selected) {
            return;
        }

        form.transform(() => ({
            name: credentials.name,
            api_key: credentials.api_key,
            region: credentials.region,
            subdomain: '',
            clockify_id: selected,
        }));
        form.post(store.url(), { preserveScroll: true, onSuccess: onDone });
    };

    if (result) {
        return (
            <ConnectionResult
                result={result}
                selected={selected}
                onSelect={setSelected}
                onBack={reset}
                onConfirm={confirm}
                processing={form.processing}
            />
        );
    }

    return <ConnectForm regions={regions} onVerified={onVerified} />;
}
