import { BellOff } from 'lucide-react';
import { EmptyState } from '@/components/app/empty-state';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    className?: string;
    description?: string;
};

export function NotificationEmpty({ className, description }: Props) {
    const { t } = useTranslation();

    return (
        <EmptyState
            icon={BellOff}
            title={t("You're all caught up")}
            description={
                description ?? t('New notifications will appear here.')
            }
            className={className}
        />
    );
}
