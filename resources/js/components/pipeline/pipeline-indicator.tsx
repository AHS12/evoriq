import { useEffect, useRef, useState } from 'react';
import { PipelineLiveDot } from '@/components/pipeline/pipeline-live-dot';
import { PipelineStatusPopover } from '@/components/pipeline/pipeline-status-popover';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { usePipelineStatus } from '@/hooks/use-pipeline-status';
import { useTranslation } from '@/hooks/use-translation';

/**
 * PIPE-08 — the global pipeline indicator in the app header. Hidden when idle,
 * it shows a toned pill while work is running (or recent failures exist) and a
 * popover listing the top active runs.
 */
export function PipelineIndicator() {
    const { t } = useTranslation();
    const { status, ready, live, pause, resume } = usePipelineStatus();
    const [open, setOpen] = useState(false);

    const previousActive = useRef(status.active_count);
    const previousFailed = useRef(status.failed_recent);
    const [announcement, setAnnouncement] = useState('');

    useEffect(() => {
        if (!ready) {
            return;
        }

        if (status.failed_recent > previousFailed.current) {
            setAnnouncement(
                t(':count background job failed.', {
                    count: status.failed_recent - previousFailed.current,
                }),
            );
        } else if (previousActive.current > 0 && status.active_count === 0) {
            setAnnouncement(t('All background jobs have finished.'));
        }

        previousActive.current = status.active_count;
        previousFailed.current = status.failed_recent;
    }, [ready, status.active_count, status.failed_recent, t]);

    if (!ready) {
        return <Skeleton className="h-8 w-24 rounded-md" />;
    }

    const idle = status.active_count === 0 && status.failed_recent === 0;

    if (idle) {
        return (
            <span aria-live="polite" className="sr-only">
                {announcement}
            </span>
        );
    }

    const running = status.active_count > 0;
    const label = running
        ? t('Background jobs: :count running', { count: status.active_count })
        : t('Background jobs: :count recent failures', {
              count: status.failed_recent,
          });

    return (
        <>
            <span aria-live="polite" className="sr-only">
                {announcement}
            </span>

            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="gap-1.5 text-muted-foreground"
                        aria-label={label}
                        title={label}
                    >
                        <PipelineLiveDot
                            tone={status.worst_status}
                            active={running && live}
                        />
                        <span className="text-xs font-medium">
                            {running
                                ? t(':count running', {
                                      count: status.active_count,
                                  })
                                : t(':count failed', {
                                      count: status.failed_recent,
                                  })}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent align="end" className="w-96 p-0">
                    <PipelineStatusPopover
                        status={status}
                        live={live}
                        onToggleLive={live ? pause : resume}
                        onNavigate={() => setOpen(false)}
                    />
                </PopoverContent>
            </Popover>
        </>
    );
}
