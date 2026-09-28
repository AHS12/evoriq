import { AppContent } from '@/components/app-content';
import { AppHeader } from '@/components/app-header';
import { AppShell } from '@/components/app-shell';
import { CommandPaletteProvider } from '@/components/command-palette/command-palette-provider';
import type { AppLayoutProps } from '@/types';

export default function AppHeaderLayout({
    children,
    breadcrumbs,
}: AppLayoutProps) {
    return (
        <CommandPaletteProvider>
            <AppShell variant="header">
                <AppHeader breadcrumbs={breadcrumbs} />
                <AppContent variant="header">{children}</AppContent>
            </AppShell>
        </CommandPaletteProvider>
    );
}
