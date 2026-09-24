import { Head, router } from '@inertiajs/react';
import { CheckCheck } from 'lucide-react';
import { DataTablePagination } from '@/components/app/data-table/data-table-pagination';
import { PageHeader } from '@/components/app/page-header';
import { NotificationEmpty } from '@/components/notification/notification-empty';
import { NotificationList } from '@/components/notification/notification-list';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDataTableFilters } from '@/hooks/use-data-table-filters';
import { index as notificationsIndex, readAll } from '@/routes/notifications';
import type {
    NotificationFilter,
    NotificationItem,
    NotificationPriorityOption,
    Paginated,
} from '@/types';

type Props = {
    feed: Paginated<NotificationItem>;
    filters: NotificationFilter;
    priorities: NotificationPriorityOption[];
};

function buildQuery(
    filters: NotificationFilter,
    page: number,
): Record<string, string> {
    const query: Record<string, string> = { page: String(page) };

    if (filters.search) {
        query.search = filters.search;
    }

    if (filters.unread) {
        query.unread = 'true';
    }

    if (filters.priority) {
        query.priority = filters.priority;
    }

    return query;
}

export default function NotificationsIndex({
    feed,
    filters,
    priorities,
}: Props) {
    // Live updates are handled by the header bell's global poll, which
    // reloads this page's `feed` prop. Do not poll again here.
    const { apply } = useDataTableFilters(notificationsIndex.url(), filters, {
        only: ['feed'],
    });

    const markAllRead = (): void => {
        router.post(
            readAll.url(),
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const goToPage = (page: number): void => {
        router.get(notificationsIndex.url(), buildQuery(filters, page), {
            preserveState: true,
            preserveScroll: true,
            only: ['feed'],
        });
    };

    return (
        <>
            <Head title="Notifications" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Notifications"
                    description="Everything that needs your attention."
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={markAllRead}
                        >
                            <CheckCheck />
                            Mark all read
                        </Button>
                    }
                />

                <div className="flex flex-wrap items-center gap-3">
                    <Tabs
                        value={filters.unread ? 'unread' : 'all'}
                        onValueChange={(value) =>
                            apply(
                                { unread: value === 'unread' ? true : null },
                                true,
                            )
                        }
                    >
                        <TabsList>
                            <TabsTrigger value="all">All</TabsTrigger>
                            <TabsTrigger value="unread">Unread</TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <Select
                        value={filters.priority ?? 'all'}
                        onValueChange={(value) =>
                            apply(
                                { priority: value === 'all' ? null : value },
                                true,
                            )
                        }
                    >
                        <SelectTrigger className="w-44">
                            <SelectValue placeholder="All priorities" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All priorities</SelectItem>
                            {priorities.map((priority) => (
                                <SelectItem
                                    key={priority.value}
                                    value={priority.value}
                                >
                                    {priority.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="rounded-lg border">
                    {feed.data.length === 0 ? (
                        <NotificationEmpty className="rounded-lg border-0" />
                    ) : (
                        <NotificationList notifications={feed.data} />
                    )}
                </div>

                {feed.meta.total > 0 && (
                    <DataTablePagination
                        meta={feed.meta}
                        onPageChange={goToPage}
                    />
                )}
            </div>
        </>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Notifications',
            href: notificationsIndex(),
        },
    ],
};
