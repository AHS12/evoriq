import { Link } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    FolderGit2,
    FolderOpen,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useActiveJobs } from '@/hooks/use-active-jobs';
import { useCan } from '@/hooks/use-can';
import { edit as settingsEdit } from '@/routes/admin/settings/general';
import { dashboard } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { index as filesIndex } from '@/routes/files';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { NavGroup, NavItem } from '@/types';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const can = useCan();
    const activeJobs = useActiveJobs();

    const workspace: NavItem[] = [];

    if (can('data-processing.view') || can('data-processing.view.all')) {
        workspace.push({
            title: 'Job activity',
            href: activityIndex(),
            icon: Activity,
            badge: activeJobs,
        });
    }

    if (can('file.view')) {
        workspace.push({
            title: 'Files',
            href: filesIndex(),
            icon: FolderOpen,
        });
    }

    const administration: NavItem[] = [];

    if (can('user.view.all')) {
        administration.push({
            title: 'Users',
            href: usersIndex(),
            icon: Users,
        });
    }

    if (can('role.view.all')) {
        administration.push({
            title: 'Roles & Permissions',
            href: rolesIndex(),
            icon: ShieldCheck,
        });
    }

    if (can('settings.view')) {
        administration.push({
            title: 'Settings',
            href: settingsEdit(),
            icon: Settings,
        });
    }

    const groups: NavGroup[] = [
        {
            title: 'Overview',
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
    ];

    if (workspace.length > 0) {
        groups.push({ title: 'Workspace', items: workspace });
    }

    if (administration.length > 0) {
        groups.push({ title: 'Administration', items: administration });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={groups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
