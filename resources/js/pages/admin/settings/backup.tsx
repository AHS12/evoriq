import { Head, router, usePoll } from '@inertiajs/react';
import { DatabaseBackup, History } from 'lucide-react';
import { useEffect, useState } from 'react';
import { BackupRuns } from '@/components/backup/backup-runs';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { SettingsForm } from '@/components/setting/settings-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Spinner } from '@/components/ui/spinner';
import { useTranslation } from '@/hooks/use-translation';
import { appLocale } from '@/lib/locale';
import { run, update } from '@/routes/admin/settings/backup';
import type { BackupRun, BackupStatus, Paginated, SettingGroup } from '@/types';

type Props = {
    group: SettingGroup;
    runs: Paginated<BackupRun>;
    status: BackupStatus;
};

export default function BackupSettings({ group, runs, status }: Props) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [tab, setTab] = useState(() =>
        new URLSearchParams(window.location.search).get('tab') === 'history'
            ? 'history'
            : 'backup',
    );

    const { start, stop } = usePoll(
        2500,
        { only: ['runs', 'status'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (status.running) {
            start();
        } else {
            stop();
        }
    }, [status.running, start, stop]);

    const changeTab = (value: string) => {
        setTab(value);

        // Keep the tab in the URL so pagination and reloads stay on it.
        const url = new URL(window.location.href);
        url.searchParams.set('tab', value);
        window.history.replaceState(null, '', url.toString());
    };

    const nextRun = status.next_run_at
        ? new Date(status.next_run_at).toLocaleString(appLocale(), {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : t('Not scheduled');

    const lastBackup = status.last_backup?.created_at
        ? new Date(status.last_backup.created_at).toLocaleString(appLocale(), {
              dateStyle: 'short',
              timeStyle: 'short',
          })
        : t('No backup yet');

    const health = status.health
        ? status.health === 'completed'
            ? t('Healthy')
            : t('Unhealthy')
        : t('No check yet');

    const runNow = () => {
        setProcessing(true);

        router.post(
            run.url(),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(false);
                },
            },
        );
    };

    return (
        <>
            <Head title={t('Backup settings')} />

            <Tabs value={tab} onValueChange={changeTab} className="space-y-6">
                <TabsList>
                    <TabsTrigger value="backup">
                        <DatabaseBackup />
                        {t('Backup')}
                    </TabsTrigger>
                    <TabsTrigger value="history">
                        <History />
                        {t('History')}
                        {status.running && (
                            <Badge variant="secondary" className="ml-1">
                                <Spinner className="size-3" />
                            </Badge>
                        )}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="backup" className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                            <Badge
                                variant={
                                    status.enabled ? 'default' : 'secondary'
                                }
                            >
                                {status.enabled ? t('Enabled') : t('Disabled')}
                            </Badge>
                            <span className="text-muted-foreground">
                                {t('Next run')}:{' '}
                                <span className="font-medium text-foreground">
                                    {nextRun}
                                </span>
                            </span>
                            <span className="text-muted-foreground">
                                {t('Last backup')}:{' '}
                                <span className="font-medium text-foreground">
                                    {lastBackup}
                                </span>
                            </span>
                            <span className="text-muted-foreground">
                                {t('Health')}:{' '}
                                <span className="font-medium text-foreground">
                                    {health}
                                </span>
                            </span>
                        </div>
                        <Button
                            size="sm"
                            disabled={status.running || processing}
                            onClick={() => setConfirming(true)}
                        >
                            {t('Run backup now')}
                        </Button>
                    </div>

                    <SettingsForm
                        group={group}
                        action={update.form()}
                        description="Changes take effect on the next scheduler tick."
                    />
                </TabsContent>

                <TabsContent value="history" className="space-y-6">
                    <BackupRuns runs={runs} />
                </TabsContent>
            </Tabs>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('Run backup now?')}
                description={t(
                    'A backup of all files and the database will be queued. It runs in the background.',
                )}
                confirmLabel={t('Run backup')}
                loading={processing}
                onConfirm={runNow}
            />
        </>
    );
}
