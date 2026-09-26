import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import type { HealthCheck } from '@/types';

const statusKeys: Record<string, string> = {
    ok: 'OK',
    warning: 'Warning',
    failed: 'Failed',
    crashed: 'Crashed',
};

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'ok':
            return 'default';
        case 'warning':
            return 'secondary';
        case 'failed':
        case 'crashed':
            return 'destructive';
        default:
            return 'outline';
    }
}

type Props = {
    health: HealthCheck[];
};

export function HealthList({ health }: Props) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle>{t('Health checks')}</CardTitle>
                <CardDescription>
                    {t('Current application health status.')}
                </CardDescription>
            </CardHeader>
            <CardContent className="divide-y">
                {health.map((check) => (
                    <div
                        key={check.name}
                        className="flex items-start justify-between gap-4 py-3"
                    >
                        <div>
                            <p className="font-medium">{check.name}</p>
                            <p className="text-sm text-muted-foreground">
                                {check.summary}
                            </p>
                        </div>
                        <Badge variant={statusVariant(check.status)}>
                            {t(statusKeys[check.status] ?? check.status)}
                        </Badge>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
