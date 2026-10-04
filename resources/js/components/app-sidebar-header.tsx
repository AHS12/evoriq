import { Search } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { ThemeToggle } from '@/components/app/theme-toggle';
import { LanguageToggle } from '@/components/app/language-toggle';
import { NotificationBell } from '@/components/notification/notification-bell';
import { PipelineIndicator } from '@/components/pipeline/pipeline-indicator';
import { ApiUsageIndicator } from '@/components/pipeline/api-usage-indicator';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCommandPalette } from '@/hooks/use-command-palette';
import { useTranslation } from '@/hooks/use-translation';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { setOpen } = useCommandPalette();
    const { t } = useTranslation();

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex items-center gap-1">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setOpen(true)}
                    aria-label={t('Open command palette')}
                    className="hidden h-9 shrink-0 gap-2 text-muted-foreground sm:inline-flex"
                >
                    <Search className="size-4" />
                    <span className="text-xs font-normal">
                        {t('Search commands…')}
                    </span>
                    <kbd className="pointer-events-none rounded border bg-muted px-1.5 font-mono text-[10px] leading-5">
                        ⌘K
                    </kbd>
                </Button>
                <ApiUsageIndicator />
                <PipelineIndicator />
                <NotificationBell />
                <LanguageToggle />
                <ThemeToggle />
            </div>
        </header>
    );
}
