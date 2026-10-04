import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatNumber } from '@/lib/format';
import type { QueueDepth as QueueDepthData } from '@/types';

type Props = {
    queue: QueueDepthData;
};

export function QueueDepth({ queue }: Props) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Queue depth')}</CardTitle>
                <CardDescription>
                    {queue.available
                        ? t('Pending jobs per channel.')
                        : t('Queue metrics unavailable (driver: :driver).', {
                              driver: queue.driver,
                          })}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
                {queue.channels.map((channel) => (
                    <div
                        key={channel.channel}
                        className="flex items-center justify-between gap-4 text-sm"
                    >
                        <span className="font-medium capitalize">
                            {channel.channel}
                        </span>
                        <span className="text-muted-foreground tabular-nums">
                            {formatNumber(channel.depth)}
                        </span>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
