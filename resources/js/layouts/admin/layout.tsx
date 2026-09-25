import { Link } from '@inertiajs/react';
import {
    Bell,
    History,
    Mail,
    Palette,
    ServerCog,
    SlidersHorizontal,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/app/page-header';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { edit as appearanceEdit } from '@/routes/admin/settings/appearance';
import { edit as auditEdit } from '@/routes/admin/settings/audit';
import { edit as developerEdit } from '@/routes/admin/settings/developer';
import { edit as generalEdit } from '@/routes/admin/settings/general';
import { edit as mailEdit } from '@/routes/admin/settings/mail';
import { edit as notificationsEdit } from '@/routes/admin/settings/notifications';
import type { NavItem } from '@/types';

export default function AdminLayout({ children }: PropsWithChildren) {
    const can = useCan();
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const items: NavItem[] = [
        {
            title: 'General',
            href: generalEdit(),
            icon: SlidersHorizontal,
        },
        {
            title: 'Mail',
            href: mailEdit(),
            icon: Mail,
        },
        {
            title: 'Notifications',
            href: notificationsEdit(),
            icon: Bell,
        },
        {
            title: 'Audit log',
            href: auditEdit(),
            icon: History,
        },
        {
            title: 'Appearance',
            href: appearanceEdit(),
            icon: Palette,
        },
    ];

    if (can('developer.view')) {
        items.push({
            title: 'Developer',
            href: developerEdit(),
            icon: ServerCog,
        });
    }

    return (
        <div className="px-4 py-6">
            <PageHeader
                title="Settings"
                description="Configure your workspace, email and developer tools."
            />

            <div className="mt-6 flex flex-col gap-6 lg:flex-row lg:gap-12">
                <aside className="w-full lg:w-56">
                    <nav
                        className="flex flex-row gap-1 overflow-x-auto lg:flex-col"
                        aria-label="Settings"
                    >
                        {items.map((item) => (
                            <Button
                                key={item.title}
                                variant="ghost"
                                size="sm"
                                asChild
                                className={cn('justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href} prefetch>
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="lg:hidden" />

                <div className="min-w-0 flex-1">{children}</div>
            </div>
        </div>
    );
}
