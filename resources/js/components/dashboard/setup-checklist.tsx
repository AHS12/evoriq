import { CheckCircle2, Circle, Clock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { SetupStep, SetupStepStatus } from '@/types';

type Props = {
    steps: SetupStep[];
};

function StepIcon({ status }: { status: SetupStepStatus }) {
    if (status === 'done') {
        return <CheckCircle2 className="size-5 shrink-0 text-primary" />;
    }

    if (status === 'coming_soon') {
        return <Clock className="size-5 shrink-0 text-muted-foreground" />;
    }

    return <Circle className="size-5 shrink-0 text-muted-foreground" />;
}

export function SetupChecklist({ steps }: Props) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Get started</CardTitle>
                <CardDescription>
                    Finish setting up Evoriq to unlock analytics.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {steps.map((step) => (
                    <div key={step.key} className="flex items-start gap-3">
                        <StepIcon status={step.status} />
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="font-medium">{step.label}</p>
                                {step.status === 'coming_soon' && (
                                    <Badge variant="outline">Coming soon</Badge>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {step.description}
                            </p>
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}
