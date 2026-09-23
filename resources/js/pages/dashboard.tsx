import { Head } from '@inertiajs/react';
import {
    Activity,
    Briefcase,
    Clock,
    DollarSign,
    Plug,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { PageHeader } from '@/components/app/page-header';
import { StatCard } from '@/components/app/stat-card';
import { SetupChecklist } from '@/components/dashboard/setup-checklist';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import type { DashboardStat, SetupStep } from '@/types';

type Props = {
    stats: DashboardStat[];
    setup: SetupStep[];
    hasAnalytics: boolean;
};

const statIcons: Record<string, LucideIcon> = {
    tracked_hours: Clock,
    billable: DollarSign,
    users: Users,
    projects: Briefcase,
};

export default function Dashboard({ stats, setup, hasAnalytics }: Props) {
    return (
        <>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Dashboard"
                    description="An overview of your workspace."
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {stats.map((stat) => (
                        <StatCard
                            key={stat.key}
                            label={stat.label}
                            value={stat.value}
                            description={stat.description ?? undefined}
                            icon={statIcons[stat.key]}
                        />
                    ))}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <SetupChecklist steps={setup} />

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Activity</CardTitle>
                            <CardDescription>
                                Tracked hours, projects and trends over time.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {hasAnalytics ? null : (
                                <EmptyState
                                    icon={Activity}
                                    title="No analytics yet"
                                    description="Connect Clockify and import your history to see tracked hours, projects and trends."
                                    action={
                                        <Button disabled>
                                            <Plug />
                                            Connect Clockify
                                        </Button>
                                    }
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
