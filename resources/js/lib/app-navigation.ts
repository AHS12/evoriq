import {
    Activity,
    FolderOpen,
    History,
    LayoutGrid,
    Settings,
    ShieldCheck,
    Users,
} from 'lucide-react';
import type { TranslationHook } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { index as activityIndex } from '@/routes/activity';
import { edit as settingsEdit } from '@/routes/admin/settings/general';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as filesIndex } from '@/routes/files';
import { index as rolesIndex } from '@/routes/roles';
import { index as usersIndex } from '@/routes/users';
import type { NavGroup, NavItem } from '@/types';

type Options = {
    can: (permission: string) => boolean;
    t: TranslationHook['t'];
    activeJobs: number;
    activeJobsLive?: boolean;
};

/**
 * FND-08 — the single, permission-gated navigation definition shared by the
 * sidebar and the command palette so the two can never drift.
 */
export function buildAppNavigation({
    can,
    t,
    activeJobs,
    activeJobsLive = false,
}: Options): NavGroup[] {
    const workspace: NavItem[] = [];

    if (can('data-processing.view') || can('data-processing.view.all')) {
        workspace.push({
            title: t('Job activity'),
            href: activityIndex(),
            icon: Activity,
            badge: activeJobs,
            live: activeJobsLive,
        });
    }

    if (can('file.view')) {
        workspace.push({
            title: t('Files'),
            href: filesIndex(),
            icon: FolderOpen,
        });
    }

    const administration: NavItem[] = [];

    if (can('user.view.all')) {
        administration.push({
            title: t('Users'),
            href: usersIndex(),
            icon: Users,
        });
    }

    if (can('role.view.all')) {
        administration.push({
            title: t('Roles & Permissions'),
            href: rolesIndex(),
            icon: ShieldCheck,
        });
    }

    if (can('settings.view')) {
        administration.push({
            title: t('Settings'),
            href: settingsEdit(),
            icon: Settings,
        });
    }

    if (can('audit.view') || can('audit.view.all')) {
        administration.push({
            title: t('Audit Log'),
            href: auditLogsIndex(),
            icon: History,
        });
    }

    const groups: NavGroup[] = [
        {
            title: t('Overview'),
            items: [
                {
                    title: t('Dashboard'),
                    href: dashboard(),
                    icon: LayoutGrid,
                },
            ],
        },
    ];

    if (workspace.length > 0) {
        groups.push({ title: t('Workspace'), items: workspace });
    }

    if (administration.length > 0) {
        groups.push({ title: t('Administration'), items: administration });
    }

    return groups;
}
