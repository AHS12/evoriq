import { CheckCircle2 } from 'lucide-react';
import { WorkspacePicker } from '@/components/connection/workspace-picker';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { ConnectionVerification } from '@/types';

type Props = {
    result: ConnectionVerification;
    selected: string | null;
    onSelect: (clockifyId: string) => void;
    onBack: () => void;
    onConfirm: () => void;
    processing: boolean;
};

function maskEmail(email: string | null): string | null {
    if (!email) {
        return null;
    }

    const [local, domain] = email.split('@');

    if (!domain) {
        return email;
    }

    return `${local.slice(0, 1)}${'*'.repeat(Math.max(1, local.length - 1))}@${domain}`;
}

/**
 * CONN-04 — the result step: detected account, plan/budget and the workspace
 * picker, with a confirm that persists the connection.
 */
export function ConnectionResult({
    result,
    selected,
    onSelect,
    onBack,
    onConfirm,
    processing,
}: Props) {
    const { t } = useTranslation();
    const profile = result.profile;
    const email = maskEmail(result.account?.email ?? null);

    const budget =
        profile?.plan === 'free'
            ? t('Free plan — :count requests/hour', {
                  count: profile.requests_per_hour ?? 0,
              })
            : t('Paid — :count requests/second', {
                  count: profile?.requests_per_second ?? 0,
              });

    return (
        <div className="space-y-5">
            <div className="flex items-start gap-3 rounded-lg border border-success/30 bg-success/5 p-3">
                <CheckCircle2
                    aria-hidden
                    className="mt-0.5 size-4 text-success"
                />
                <div className="min-w-0">
                    <p className="text-sm font-medium">
                        {t('Connected account')}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {result.account?.name ?? t('Clockify account')}
                        {email ? ` · ${email}` : ''}
                    </p>
                </div>
            </div>

            <div className="rounded-lg border bg-muted/30 p-3">
                <p className="text-xs text-muted-foreground">
                    {t('Detected plan')}
                </p>
                <p className="text-sm font-medium">{budget}</p>
            </div>

            <WorkspacePicker
                workspaces={result.workspaces}
                value={selected}
                onChange={onSelect}
            />

            <div className="flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    loading={processing}
                    disabled={selected === null}
                    onClick={onConfirm}
                >
                    {t('Use this connection')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={processing}
                    onClick={onBack}
                >
                    {t('Back')}
                </Button>
            </div>
        </div>
    );
}
