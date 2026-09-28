import { router } from '@inertiajs/react';
import {
    Bell,
    LogOut,
    Moon,
    Sun,
    UserRound,
    type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { useActiveJobs } from '@/hooks/use-active-jobs';
import { useAppearancePreferences } from '@/hooks/use-appearance';
import { useCan } from '@/hooks/use-can';
import { useCommandPalette } from '@/hooks/use-command-palette';
import { useTranslation } from '@/hooks/use-translation';
import { buildAppNavigation } from '@/lib/app-navigation';
import {
    pushCommandRecent,
    readCommandRecents,
    type CommandRecent,
} from '@/lib/command-recents';
import { toUrl } from '@/lib/utils';
import { logout } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import { edit as profileEdit } from '@/routes/profile';

type Command = {
    id: string;
    label: string;
    icon?: LucideIcon;
    run: () => void;
};

/**
 * FND-08 — global ⌘K command palette. Navigation derives from the shared
 * permission-gated navigation; actions are global; the last five commands are
 * remembered locally.
 */
export function CommandPalette() {
    const { open, setOpen } = useCommandPalette();
    const { t } = useTranslation();
    const can = useCan();
    const activeJobs = useActiveJobs();
    const { resolvedAppearance, update } = useAppearancePreferences();
    const [recents, setRecents] = useState<CommandRecent[]>([]);

    const handleOpenChange = (next: boolean) => {
        if (next) {
            setRecents(readCommandRecents());
        }

        setOpen(next);
    };

    const navigationCommands: Command[] = buildAppNavigation({
        can,
        t,
        activeJobs,
    }).flatMap((group) =>
        group.items.map((item) => {
            const href = toUrl(item.href);

            return {
                id: `nav:${href}`,
                label: item.title,
                icon: item.icon ?? undefined,
                run: () => router.visit(href),
            };
        }),
    );

    const isDark = resolvedAppearance === 'dark';

    const actionCommands: Command[] = [
        {
            id: 'action:theme',
            label: isDark
                ? t('Switch to light mode')
                : t('Switch to dark mode'),
            icon: isDark ? Sun : Moon,
            run: () => update({ mode: isDark ? 'light' : 'dark' }),
        },
        {
            id: 'action:account',
            label: t('Account'),
            icon: UserRound,
            run: () => router.visit(profileEdit().url),
        },
        {
            id: 'action:notifications',
            label: t('Notifications'),
            icon: Bell,
            run: () => router.visit(notificationsIndex().url),
        },
        {
            id: 'action:logout',
            label: t('Log out'),
            icon: LogOut,
            run: () => router.post(logout().url),
        },
    ];

    const commandsById = new Map(
        [...navigationCommands, ...actionCommands].map((command) => [
            command.id,
            command,
        ]),
    );

    const recentCommands = recents
        .map((recent) => commandsById.get(recent.id))
        .filter((command): command is Command => command !== undefined);

    const run = (command: Command) => {
        setOpen(false);
        setRecents(pushCommandRecent(command.id));
        command.run();
    };

    return (
        <CommandDialog
            open={open}
            onOpenChange={handleOpenChange}
            title={t('Command palette')}
            description={t('Search commands…')}
        >
            <CommandInput placeholder={t('Search commands…')} />
            <CommandList className="scrollbar-hidden">
                <CommandEmpty>{t('No matching commands')}</CommandEmpty>

                {recentCommands.length > 0 && (
                    <CommandGroup heading={t('Recent')}>
                        {recentCommands.map((command) => (
                            <CommandRow
                                key={`recent-${command.id}`}
                                command={command}
                                prefix="recent"
                                onRun={run}
                            />
                        ))}
                    </CommandGroup>
                )}

                <CommandGroup heading={t('Navigation')}>
                    {navigationCommands.map((command) => (
                        <CommandRow
                            key={command.id}
                            command={command}
                            prefix="nav"
                            onRun={run}
                        />
                    ))}
                </CommandGroup>

                <CommandGroup heading={t('Actions')}>
                    {actionCommands.map((command) => (
                        <CommandRow
                            key={command.id}
                            command={command}
                            prefix="action"
                            onRun={run}
                        />
                    ))}
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}

function CommandRow({
    command,
    prefix,
    onRun,
}: {
    command: Command;
    prefix: string;
    onRun: (command: Command) => void;
}) {
    const Icon = command.icon;

    return (
        <CommandItem
            value={`${prefix} ${command.id} ${command.label}`}
            onSelect={() => onRun(command)}
        >
            {Icon ? <Icon /> : null}
            <span>{command.label}</span>
        </CommandItem>
    );
}
