import { CalendarDays, MailCheck, Pencil, ShieldCheck } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { UserStatusBadge } from '@/components/user/user-status-badge';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from '@/hooks/use-translation';
import type { User } from '@/types';
import { appLocale } from '@/lib/locale';

type Props = {
    user: User | null;
    onOpenChange: (open: boolean) => void;
    onEdit?: (user: User) => void;
};

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString(appLocale(), {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function DetailRow({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-start justify-between gap-4">
            <span className="text-sm text-muted-foreground">{label}</span>
            <div className="flex min-w-0 flex-wrap items-center justify-end gap-1 text-right text-sm font-medium">
                {children}
            </div>
        </div>
    );
}

export function UserDetailsSheet({ user, onOpenChange, onEdit }: Props) {
    const getInitials = useInitials();
    const { t } = useTranslation();

    return (
        <Sheet open={user !== null} onOpenChange={onOpenChange}>
            <SheetContent className="flex flex-col gap-0 overflow-y-auto sm:max-w-md">
                {user && (
                    <>
                        <SheetHeader>
                            <div className="flex items-center gap-3">
                                <Avatar className="size-14">
                                    {user.avatar && (
                                        <AvatarImage
                                            src={user.avatar}
                                            alt={user.name}
                                        />
                                    )}
                                    <AvatarFallback className="text-lg">
                                        {getInitials(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="min-w-0">
                                    <SheetTitle className="truncate">
                                        {user.name}
                                    </SheetTitle>
                                    <SheetDescription className="truncate">
                                        {user.email}
                                    </SheetDescription>
                                </div>
                            </div>
                        </SheetHeader>

                        <div className="flex-1 space-y-6 px-4 pb-6">
                            <UserStatusBadge
                                status={user.status}
                                label={user.status_label}
                            />

                            <div className="space-y-3">
                                <DetailRow label={t('Email')}>
                                    <span className="truncate">
                                        {user.email}
                                    </span>
                                </DetailRow>
                                <DetailRow label={t('Email verified')}>
                                    {user.email_verified_at ? (
                                        <>
                                            <MailCheck className="size-4 text-success" />
                                            {formatDate(user.email_verified_at)}
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            {t('Not verified')}
                                        </span>
                                    )}
                                </DetailRow>
                            </div>

                            <Separator />

                            <div className="space-y-3">
                                <p className="text-sm font-medium">
                                    {t('Roles')}
                                </p>
                                {user.roles && user.roles.length > 0 ? (
                                    <div className="flex flex-wrap gap-1">
                                        {user.roles.map((name) => (
                                            <Badge key={name} variant="outline">
                                                <ShieldCheck className="size-3" />
                                                {name}
                                            </Badge>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        —
                                    </p>
                                )}
                            </div>

                            <Separator />

                            <div className="space-y-3">
                                <p className="text-sm font-medium">
                                    {t('Timeline')}
                                </p>
                                <DetailRow label={t('Created')}>
                                    <CalendarDays className="size-4 text-muted-foreground" />
                                    {formatDate(user.created_at)}
                                </DetailRow>
                                <DetailRow label={t('Last updated')}>
                                    <CalendarDays className="size-4 text-muted-foreground" />
                                    {formatDate(user.updated_at)}
                                </DetailRow>
                            </div>
                        </div>

                        {onEdit && (
                            <SheetFooter>
                                <Button onClick={() => onEdit(user)}>
                                    <Pencil />
                                    {t('Edit user')}
                                </Button>
                            </SheetFooter>
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
