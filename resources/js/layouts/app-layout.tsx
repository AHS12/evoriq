import { CommandPaletteProvider } from '@/components/command-palette/command-palette-provider';
import { JobQueuedDialog } from '@/components/data-processing/job-queued-dialog';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <CommandPaletteProvider>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
                <JobQueuedDialog />
            </AppLayoutTemplate>
        </CommandPaletteProvider>
    );
}
