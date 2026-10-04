import { router } from '@inertiajs/react';
import {
    KeyRound,
    MoreHorizontal,
    Pencil,
    Power,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { RenameConnectionDialog } from '@/components/connection/rename-connection-dialog';
import { RotateKeyDialog } from '@/components/connection/rotate-key-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslation } from '@/hooks/use-translation';
import { postJson } from '@/lib/http';
import { toast } from '@/lib/toast';
import { disable, enable, reverify } from '@/routes/connections';
import type { Connection, ConnectionVerification } from '@/types';

type Props = {
    connection: Connection;
    canRotate: boolean;
};

/**
 * CONN-05/CONN-06 — per-connection lifecycle actions: rename, re-verify, rotate
 * the API key and enable/disable. Rotation is gated separately by the
 * credential permission.
 */
export function ConnectionCardActions({ connection, canRotate }: Props) {
    const { t } = useTranslation();
    const [renameOpen, setRenameOpen] = useState(false);
    const [rotateOpen, setRotateOpen] = useState(false);
    const [toggleOpen, setToggleOpen] = useState(false);
    const [reverifying, setReverifying] = useState(false);

    const isDisabled = connection.status === 'disabled';

    const runReverify = async (): Promise<void> => {
        setReverifying(true);

        try {
            const result = await postJson<ConnectionVerification>(
                reverify.url(connection.id),
                {},
            );

            if (result.ok) {
                toast.success(t('Connection verified.'));
                router.reload();

                return;
            }

            toast.error(
                result.error?.label
                    ? t(result.error.label)
                    : t('Verification failed'),
            );
        } catch {
            toast.error(t('Verification failed'));
        } finally {
            setReverifying(false);
        }
    };

    const runToggle = (): void => {
        const route = isDisabled ? enable : disable;

        router.post(
            route.url(connection.id),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setToggleOpen(false),
            },
        );
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('Actions for :name', {
                            name: connection.name,
                        })}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem onSelect={() => setRenameOpen(true)}>
                        <Pencil className="mr-2 size-4" />
                        {t('Rename')}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        disabled={reverifying}
                        onSelect={() => void runReverify()}
                    >
                        <RefreshCw className="mr-2 size-4" />
                        {t('Re-verify')}
                    </DropdownMenuItem>
                    {canRotate && (
                        <DropdownMenuItem onSelect={() => setRotateOpen(true)}>
                            <KeyRound className="mr-2 size-4" />
                            {t('Rotate API key')}
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant={isDisabled ? undefined : 'destructive'}
                        onSelect={() => setToggleOpen(true)}
                    >
                        <Power className="mr-2 size-4" />
                        {isDisabled ? t('Enable') : t('Disable')}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <RenameConnectionDialog
                connection={connection}
                open={renameOpen}
                onOpenChange={setRenameOpen}
            />

            <RotateKeyDialog
                connection={connection}
                open={rotateOpen}
                onOpenChange={setRotateOpen}
            />

            <ConfirmDialog
                open={toggleOpen}
                onOpenChange={setToggleOpen}
                title={
                    isDisabled
                        ? t('Enable connection?')
                        : t('Disable connection?')
                }
                description={
                    isDisabled
                        ? t(
                              'Scheduled and manual syncs will resume for this connection.',
                          )
                        : t(
                              'Scheduled and manual syncs will stop until you re-enable it. Imported data is kept.',
                          )
                }
                confirmLabel={isDisabled ? t('Enable') : t('Disable')}
                destructive={!isDisabled}
                onConfirm={runToggle}
            />
        </>
    );
}
