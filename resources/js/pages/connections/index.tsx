import { Head } from '@inertiajs/react';
import { Plug, Plus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { ConnectDialog } from '@/components/connection/connect-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatRelativeTime } from '@/lib/format';
import { index as connectionsIndex } from '@/routes/connections';
import type { Connection, Paginated, RegionOption } from '@/types';

type Props = {
    connections: Paginated<Connection>;
    regions: RegionOption[];
    canCreate: boolean;
};

function ConnectionCard({ connection }: { connection: Connection }) {
    const { t } = useTranslation();

    return (
        <article
            data-slot="connection-card"
            className="flex flex-col gap-3 rounded-xl border bg-card p-4 transition-smooth-fast hover:border-primary/40"
        >
            <div className="flex items-start justify-between gap-2">
                <div className="flex min-w-0 items-center gap-2">
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <Plug
                            aria-hidden
                            className="size-4 text-muted-foreground"
                        />
                    </span>
                    <span className="truncate font-medium">
                        {connection.name}
                    </span>
                </div>
                <Badge
                    variant={
                        connection.status === 'active' ? 'default' : 'secondary'
                    }
                >
                    {connection.status_label}
                </Badge>
            </div>

            <div className="flex flex-wrap gap-2">
                <Badge variant="outline">{connection.region_label}</Badge>
                <Badge variant="outline">
                    {connection.plan.is_free ? t('Free') : t('Paid')}
                </Badge>
            </div>

            <dl className="mt-auto space-y-1 text-xs text-muted-foreground">
                <div className="flex items-center justify-between gap-3">
                    <dt>{t('Workspace')}</dt>
                    <dd className="truncate">
                        {connection.workspace.id
                            ? connection.workspace.id
                            : t('None')}
                    </dd>
                </div>
                <div className="flex items-center justify-between gap-3">
                    <dt>{t('Last verified')}</dt>
                    <dd className="truncate">
                        {connection.last_verified_at
                            ? formatRelativeTime(connection.last_verified_at)
                            : t('Never')}
                    </dd>
                </div>
            </dl>
        </article>
    );
}

export default function ConnectionsIndex({
    connections,
    regions,
    canCreate,
}: Props) {
    const { t } = useTranslation();
    const [connectOpen, setConnectOpen] = useState(false);

    const openConnect = (): void => setConnectOpen(true);

    return (
        <>
            <Head title={t('Connections')} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={t('Connections')}
                    description={t(
                        'Connect Clockify to import and analyze your time data.',
                    )}
                    actions={
                        canCreate ? (
                            <Button onClick={openConnect}>
                                <Plus />
                                {t('Add connection')}
                            </Button>
                        ) : undefined
                    }
                />

                {connections.data.length === 0 ? (
                    <EmptyState
                        icon={Plug}
                        title={t('No connections yet')}
                        description={t(
                            'Add a Clockify connection to start importing time data.',
                        )}
                        action={
                            canCreate ? (
                                <Button onClick={openConnect}>
                                    <Plus />
                                    {t('Add connection')}
                                </Button>
                            ) : undefined
                        }
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {connections.data.map((connection) => (
                            <ConnectionCard
                                key={connection.id}
                                connection={connection}
                            />
                        ))}
                    </div>
                )}
            </div>

            <ConnectDialog
                open={connectOpen}
                onOpenChange={setConnectOpen}
                regions={regions}
            />
        </>
    );
}

ConnectionsIndex.layout = {
    breadcrumbs: [{ title: 'Connections', href: connectionsIndex() }],
};
