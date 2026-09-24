import {
    Download,
    FileText,
    Upload,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const ICONS: Record<string, LucideIcon> = {
    download: Download,
    upload: Upload,
    'file-text': FileText,
    users: Users,
};

type Props = {
    icon: string | null;
    className?: string;
};

export function JobTypeIcon({ icon, className }: Props) {
    const Icon = (icon !== null && ICONS[icon]) || FileText;

    return (
        <div
            className={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted',
                className,
            )}
        >
            <Icon className="size-4 text-muted-foreground" />
        </div>
    );
}
