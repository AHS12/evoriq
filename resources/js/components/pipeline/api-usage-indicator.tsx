import { Gauge } from 'lucide-react';
import { useState } from 'react';
import { ApiUsagePopover } from '@/components/pipeline/api-usage-popover';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useApiUsage } from '@/hooks/use-api-usage';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

/**
 * SYNC-17 — the navbar API-budget indicator. Shows `API remaining/limit` on
 * Free (hourly) plans and a per-second rate label on paid plans, toned by
 * headroom, with a detail popover. Hidden when no connection is configured.
 */
export function ApiUsageIndicator() {
    const { t } = useTranslation();
    const { usage } = useApiUsage();
    const [open, setOpen] = useState(false);

    if (usage === null) {
        return null;
    }

    const free = usage.plan === 'free';
    const label = free
        ? t('Clockify API: :remaining of :limit requests remaining', {
              remaining: usage.remaining,
              limit: usage.limit,
          })
        : t('Clockify API: up to :limit requests per second', {
              limit: usage.limit,
          });

    const value = free
        ? t('API :remaining/:limit', {
              remaining: usage.remaining,
              limit: usage.limit,
          })
        : t('API :limit/s', { limit: usage.limit });

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={label}
                    title={label}
                    className={cn(
                        'gap-1.5 text-xs font-medium text-muted-foreground',
                        usage.low && !usage.exhausted && 'text-warning',
                        usage.exhausted && 'text-destructive',
                    )}
                >
                    <Gauge className="size-4" />
                    <span className="tabular-nums">{value}</span>
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
                <ApiUsagePopover
                    usage={usage}
                    onNavigate={() => setOpen(false)}
                />
            </PopoverContent>
        </Popover>
    );
}
