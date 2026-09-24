import { Pause, Play, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import type {
    JobStatus,
    JobStatusOption,
    JobType,
    JobTypeOption,
} from '@/types';

type Props = {
    search: string;
    onSearchChange: (value: string) => void;
    type: JobType | 'all';
    onTypeChange: (value: JobType | 'all') => void;
    status: JobStatus | 'all';
    onStatusChange: (value: JobStatus | 'all') => void;
    statuses: JobStatusOption[];
    types: JobTypeOption[];
    live: boolean;
    onToggleLive: () => void;
};

export function JobFilters({
    search,
    onSearchChange,
    type,
    onTypeChange,
    status,
    onStatusChange,
    statuses,
    types,
    live,
    onToggleLive,
}: Props) {
    const segments: { value: JobType | 'all'; label: string }[] = [
        { value: 'all', label: 'All' },
        ...types.map((option) => ({
            value: option.value,
            label: `${option.label}s`,
        })),
    ];

    return (
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div className="inline-flex items-center gap-1 rounded-lg border bg-muted/40 p-1">
                {segments.map((segment) => (
                    <button
                        key={segment.value}
                        type="button"
                        onClick={() => onTypeChange(segment.value)}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                            type === segment.value
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        {segment.label}
                    </button>
                ))}
            </div>

            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative w-full sm:w-64">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder="Search jobs…"
                        className="pl-9"
                    />
                </div>

                <Select
                    value={status}
                    onValueChange={(value) =>
                        onStatusChange(value as JobStatus | 'all')
                    }
                >
                    <SelectTrigger className="w-full sm:w-40">
                        <SelectValue placeholder="All statuses" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All statuses</SelectItem>
                        {statuses.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Button
                    type="button"
                    variant="outline"
                    onClick={onToggleLive}
                    className="gap-2"
                >
                    {live ? (
                        <>
                            <Pause className="size-4" />
                            Live
                        </>
                    ) : (
                        <>
                            <Play className="size-4" />
                            Paused
                        </>
                    )}
                </Button>
            </div>
        </div>
    );
}
