import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { HealthCheck } from '@/types';

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
    return (
        <Card>
            <CardHeader>
                <CardTitle>Health checks</CardTitle>
                <CardDescription>
                    Current application health status.
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
                            {check.status}
                        </Badge>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
