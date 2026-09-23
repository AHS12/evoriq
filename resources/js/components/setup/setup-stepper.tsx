import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    steps: string[];
    current: number;
    reachable?: number;
    onNavigate?: (index: number) => void;
    className?: string;
};

export function SetupStepper({
    steps,
    current,
    reachable,
    onNavigate,
    className,
}: Props) {
    const max = reachable ?? current;

    return (
        <ol className={cn('flex flex-wrap items-center gap-2', className)}>
            {steps.map((label, index) => {
                const done = index < current;
                const active = index === current;
                const clickable =
                    onNavigate !== undefined && index <= max && !active;

                const content = (
                    <>
                        <span
                            className={cn(
                                'flex size-7 items-center justify-center rounded-full border text-xs font-medium',
                                done &&
                                    'border-primary bg-primary text-primary-foreground',
                                active && 'border-primary text-primary',
                                !done &&
                                    !active &&
                                    'border-muted-foreground/30 text-muted-foreground',
                                clickable &&
                                    'group-hover:border-primary group-hover:text-primary',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : index + 1}
                        </span>
                        <span
                            className={cn(
                                'text-sm',
                                active
                                    ? 'font-medium text-foreground'
                                    : 'text-muted-foreground',
                                clickable && 'group-hover:text-foreground',
                            )}
                        >
                            {label}
                        </span>
                    </>
                );

                return (
                    <li key={label} className="flex items-center gap-2">
                        {clickable ? (
                            <button
                                type="button"
                                onClick={() => onNavigate(index)}
                                className="group flex items-center gap-2 rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                {content}
                            </button>
                        ) : (
                            <div
                                className="flex items-center gap-2"
                                aria-current={active ? 'step' : undefined}
                            >
                                {content}
                            </div>
                        )}
                        {index < steps.length - 1 && (
                            <span className="mx-1 h-px w-6 bg-border" />
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
