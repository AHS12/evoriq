import { Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';

type Props = {
    className?: string;
};

export function SystemRoleBadge({ className }: Props) {
    const { t } = useTranslation();

    return (
        <Badge variant="secondary" className={cn('gap-1', className)}>
            <Lock className="size-3" />
            {t('System')}
        </Badge>
    );
}
