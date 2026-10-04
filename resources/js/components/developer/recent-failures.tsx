import { Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatRelativeTime } from '@/lib/format';
import type { PipelineRecentFailure } from '@/types';

type Props = {
    failures: PipelineRecentFailure[];
};

export function RecentFailures({ failures }: Props) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Recent failures')}</CardTitle>
                <CardDescription>
                    {t('Failed runs and how to recover them.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {failures.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('No failures — everything is running smoothly.')}
                    </p>
                ) : (
                    failures.map((failure) => (
                        <div
                            key={failure.id}
                            className="space-y-2 rounded-md border p-3"
                        >
                            <div className="flex items-center justify-between gap-2">
                                <Link
                                    href={failure.action_url}
                                    className="truncate text-sm font-medium hover:underline"
                                >
                                    {failure.type_label}
                                    {failure.entity_label
                                        ? ` · ${failure.entity_label}`
                                        : ''}
                                </Link>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {failure.failed_at
                                        ? formatRelativeTime(failure.failed_at)
                                        : ''}
                                </span>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {failure.reason_label ??
                                    failure.error_message ??
                                    t('Failed')}
                            </p>
                            {failure.hint && (
                                <p className="text-xs text-muted-foreground">
                                    {failure.hint}
                                </p>
                            )}
                            {failure.action === 'retry' && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        router.post(failure.retry_url)
                                    }
                                >
                                    <RefreshCw className="size-4" />
                                    {t('Retry')}
                                </Button>
                            )}
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
