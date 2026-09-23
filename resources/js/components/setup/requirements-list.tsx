import { Check, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Requirement } from '@/types/setup';

export function RequirementsList({
    requirements,
}: {
    requirements: Requirement[];
}) {
    return (
        <ul className="divide-y rounded-lg border">
            {requirements.map((requirement) => (
                <li
                    key={requirement.key}
                    className="flex items-center justify-between gap-4 p-3"
                >
                    <div className="flex items-center gap-3">
                        <span
                            className={cn(
                                'flex size-6 items-center justify-center rounded-full',
                                requirement.passed
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-destructive/10 text-destructive',
                            )}
                        >
                            {requirement.passed ? (
                                <Check className="size-3.5" />
                            ) : (
                                <X className="size-3.5" />
                            )}
                        </span>
                        <span className="text-sm font-medium">
                            {requirement.label}
                        </span>
                    </div>
                    <span className="text-xs text-muted-foreground">
                        {requirement.detail}
                    </span>
                </li>
            ))}
        </ul>
    );
}
